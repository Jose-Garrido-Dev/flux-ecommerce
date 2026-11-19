<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Única puerta para cambiar stock (módulo 6).
 * - Cada cambio del stock físico deja un movimiento con stock anterior y posterior.
 * - Las filas de inventory_levels se bloquean (SELECT ... FOR UPDATE) o se actualizan de forma atómica,
 *   así dos compras nunca toman la misma última unidad.
 * - Los movimientos no se editan: se corrigen con contramovimientos.
 */
class InventoryService
{
    public const DEFAULT_RESERVATION_MINUTES = 30;

    public function __construct(private readonly SettingsRepository $settings) {}

    /** Ficha de inventario y fila de stock en la bodega principal para una variante nueva. */
    public function initialize(ProductVariant $variant): void
    {
        InventoryItem::query()->firstOrCreate(['variant_id' => $variant->id], [
            'track_stock' => (bool) $this->settings->get('inventory', 'track_stock_default', true),
            'min_stock' => (int) $this->settings->get('inventory', 'min_stock_default', 0),
        ]);
        if ($warehouse = Warehouse::query()->where('is_default', true)->first()) {
            InventoryLevel::query()->firstOrCreate(['variant_id' => $variant->id, 'warehouse_id' => $warehouse->id]);
        }
    }

    public function item(ProductVariant|int $variant): InventoryItem
    {
        $id = $variant instanceof ProductVariant ? $variant->id : $variant;

        return InventoryItem::query()->firstOrCreate(['variant_id' => $id]);
    }

    /**
     * Registra un movimiento del stock físico. $quantity es siempre positiva: el tipo define el signo.
     * Una salida no puede dejar el stock físico bajo lo reservado (ni negativo).
     */
    public function move(
        ProductVariant|int $variant,
        Warehouse|int $warehouse,
        MovementType $type,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor a cero.']);
        }
        if ($type->sign() === 0) {
            throw ValidationException::withMessages(['type' => 'Ese tipo de movimiento no se registra a mano.']);
        }

        return DB::transaction(function () use ($variant, $warehouse, $type, $quantity, $reason, $referenceType, $referenceId) {
            $level = $this->lockLevel($variant, $warehouse);
            $delta = $type->sign() * $quantity;

            if ($delta < 0 && $level->on_hand + $delta < $level->reserved) {
                $free = max(0, $level->on_hand - $level->reserved);
                throw ValidationException::withMessages([
                    'quantity' => "Solo hay $free unidades disponibles en esa bodega (físico {$level->on_hand}, reservado {$level->reserved}).",
                ]);
            }

            $movement = $this->apply($level, $type, $delta, 0, $reason, $referenceType, $referenceId);
            $this->audit($movement);

            return $movement;
        });
    }

    /** Ajuste por conteo físico: deja el stock en $counted registrando la diferencia como ajuste. */
    public function countTo(ProductVariant|int $variant, Warehouse|int $warehouse, int $counted, ?string $reason = null): ?StockMovement
    {
        if ($counted < 0) {
            throw ValidationException::withMessages(['counted' => 'El conteo no puede ser negativo.']);
        }

        return DB::transaction(function () use ($variant, $warehouse, $counted, $reason) {
            $level = $this->lockLevel($variant, $warehouse);
            $diff = $counted - $level->on_hand;
            if ($diff === 0) {
                return null;
            }
            if ($counted < $level->reserved) {
                throw ValidationException::withMessages([
                    'counted' => "Hay {$level->reserved} unidades reservadas en esa bodega: el conteo no puede ser menor. Libera reservas primero.",
                ]);
            }
            $movement = $this->apply($level, $diff > 0 ? MovementType::AdjustIn : MovementType::AdjustOut, $diff, 0,
                $reason ?: 'Ajuste por conteo físico', 'count', null);
            $this->audit($movement);

            return $movement;
        });
    }

    /** Contramovimiento: anula el efecto de un movimiento sin modificarlo. Solo una vez por movimiento. */
    public function reverse(StockMovement $movement, string $reason): StockMovement
    {
        return DB::transaction(function () use ($movement, $reason) {
            $movement = StockMovement::query()->whereKey($movement->id)->lockForUpdate()->firstOrFail();
            if (! $movement->isReversible()) {
                throw ValidationException::withMessages([
                    'movement' => 'Este movimiento no se puede revertir (ya fue revertido, es una venta, una transferencia o una reserva).',
                ]);
            }
            $level = $this->lockLevel($movement->variant_id, $movement->warehouse_id);
            $delta = -$movement->quantity;
            if ($delta < 0 && $level->on_hand + $delta < $level->reserved) {
                throw ValidationException::withMessages([
                    'movement' => "No hay stock suficiente para revertir: disponible {$level->available()}.",
                ]);
            }

            $reversal = $this->apply($level, MovementType::Reversal, $delta, 0, $reason, 'movement', (string) $movement->id, $movement->id);
            $this->audit($reversal);

            return $reversal;
        });
    }

    /**
     * Transferencia entre bodegas: una salida y una entrada por producto, en una sola transacción.
     *
     * @param  list<array{variant_id: int, quantity: int}>  $items
     */
    public function transfer(Warehouse|int $from, Warehouse|int $to, array $items, ?string $notes = null): StockTransfer
    {
        $fromId = $from instanceof Warehouse ? $from->id : $from;
        $toId = $to instanceof Warehouse ? $to->id : $to;
        if ($fromId === $toId) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'La bodega de destino debe ser distinta a la de origen.']);
        }
        $items = collect($items)->groupBy('variant_id')->map(fn ($rows, $variantId) => [
            'variant_id' => (int) $variantId, 'quantity' => (int) $rows->sum('quantity'),
        ])->filter(fn ($i) => $i['quantity'] > 0)->values();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con cantidad.']);
        }

        return DB::transaction(function () use ($fromId, $toId, $items, $notes) {
            $transfer = StockTransfer::query()->create([
                'from_warehouse_id' => $fromId, 'to_warehouse_id' => $toId, 'status' => 'completed',
                'notes' => $notes, 'user_id' => Auth::id(), 'completed_at' => now(),
            ]);

            // Orden fijo de bloqueo para evitar bloqueos mutuos entre transferencias simultáneas
            foreach ($items->sortBy('variant_id') as $item) {
                $source = $this->lockLevel($item['variant_id'], $fromId);
                if ($source->available() < $item['quantity']) {
                    $sku = ProductVariant::query()->whereKey($item['variant_id'])->value('sku');
                    throw ValidationException::withMessages([
                        'items' => "$sku: solo hay {$source->available()} unidades disponibles en la bodega de origen.",
                    ]);
                }
                $target = $this->lockLevel($item['variant_id'], $toId);
                $transfer->items()->create($item);
                $this->apply($source, MovementType::TransferOut, -$item['quantity'], 0, $notes, 'transfer', (string) $transfer->id, null, $transfer->id);
                $this->apply($target, MovementType::TransferIn, $item['quantity'], 0, $notes, 'transfer', (string) $transfer->id, null, $transfer->id);
            }
            app(AuditLogger::class)->record('StockTransfer.completed', $transfer, [], ['items' => $items->all()]);

            return $transfer;
        });
    }

    // ---------------------------------------------------------------- Reservas

    /**
     * Reserva stock para un carrito o pedido. Idempotente por (referencia, variante):
     * repetir la llamada no duplica la reserva, solo renueva el vencimiento o ajusta la cantidad.
     * Devuelve null si la variante no controla stock (no hace falta reservar).
     */
    public function reserve(ProductVariant|int $variant, int $quantity, string $referenceType, string $referenceId, ?int $minutes = null): ?StockReservation
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'La cantidad debe ser mayor a cero.']);
        }
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;
        $item = $this->item($variantId);
        if (! $item->track_stock) {
            return null;
        }
        $expiresAt = now()->addMinutes($minutes ?? $this->reservationMinutes());

        return DB::transaction(function () use ($variantId, $item, $quantity, $referenceType, $referenceId, $expiresAt) {
            $existing = StockReservation::query()->where([
                'reference_type' => $referenceType, 'reference_id' => $referenceId,
                'variant_id' => $variantId, 'status' => 'active',
            ])->lockForUpdate()->first();

            if ($existing && $existing->quantity === $quantity) {
                $existing->update(['expires_at' => $expiresAt]);

                return $existing;
            }
            if ($existing) {
                $this->release($existing, 'released', 'Cambio de cantidad');
            }

            $levels = InventoryLevel::query()
                ->where('variant_id', $variantId)
                ->whereHas('warehouse', fn ($q) => $q->where('status', 'active'))
                ->join('warehouses', 'warehouses.id', '=', 'inventory_levels.warehouse_id')
                ->orderByDesc('warehouses.is_default')->orderBy('warehouses.priority')->orderBy('warehouses.id')
                ->get(['inventory_levels.id', 'inventory_levels.warehouse_id']);

            $warehouseId = null;
            foreach ($levels as $level) {
                // Actualización atómica: solo afecta la fila si todavía queda stock libre suficiente
                $updated = DB::table('inventory_levels')
                    ->where('id', $level->id)
                    ->whereRaw('on_hand - reserved >= ?', [$quantity])
                    ->update(['reserved' => DB::raw('reserved + '.(int) $quantity), 'updated_at' => now()]);
                if ($updated === 1) {
                    $warehouseId = $level->warehouse_id;
                    break;
                }
            }

            if ($warehouseId === null && $item->allow_backorder) {
                // Sobreventa autorizada: se reserva igual en la bodega principal (el físico queda por debajo)
                $warehouseId = $levels->first()?->warehouse_id ?? Warehouse::default()->id;
                $level = $this->lockLevel($variantId, $warehouseId);
                $level->increment('reserved', $quantity);
            }

            if ($warehouseId === null) {
                throw ValidationException::withMessages([
                    'stock' => 'No hay stock suficiente de este producto.',
                ]);
            }

            $reservation = StockReservation::query()->create([
                'reference_type' => $referenceType, 'reference_id' => $referenceId, 'variant_id' => $variantId,
                'warehouse_id' => $warehouseId, 'quantity' => $quantity, 'expires_at' => $expiresAt,
            ]);
            $this->log(InventoryLevel::query()->where(['variant_id' => $variantId, 'warehouse_id' => $warehouseId])->firstOrFail(),
                MovementType::Reservation, $quantity, $referenceType, $referenceId);

            return $reservation;
        });
    }

    /** Libera una reserva activa. Si ya no está activa no hace nada (idempotente). */
    public function release(StockReservation $reservation, string $status = 'released', ?string $reason = null): bool
    {
        return DB::transaction(function () use ($reservation, $status, $reason) {
            $reservation = StockReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status !== 'active') {
                return false;
            }
            $level = $this->lockLevel($reservation->variant_id, $reservation->warehouse_id);
            $level->reserved = max(0, $level->reserved - $reservation->quantity);
            $level->save();
            $reservation->update(['status' => $status, 'closed_at' => now()]);
            $this->log($level, MovementType::ReservationRelease, -$reservation->quantity,
                $reservation->reference_type, $reservation->reference_id, $reason ?? ($status === 'expired' ? 'Reserva vencida' : null));

            return true;
        });
    }

    /**
     * Convierte la reserva en venta: descuenta el stock físico y lo reservado.
     * Idempotente: si la reserva ya fue vendida (p. ej. un webhook de pago repetido) no descuenta de nuevo.
     * Devuelve false si no había nada que consumir.
     */
    public function consume(StockReservation $reservation, ?string $orderReference = null): bool
    {
        return DB::transaction(function () use ($reservation, $orderReference) {
            $reservation = StockReservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($reservation->status === 'consumed') {
                return false;
            }
            if ($reservation->status !== 'active') {
                throw ValidationException::withMessages([
                    'reservation' => 'La reserva ya no está activa ('.StockReservation::STATUSES[$reservation->status].'). Hay que reservar de nuevo.',
                ]);
            }
            $level = $this->lockLevel($reservation->variant_id, $reservation->warehouse_id);
            $level->reserved = max(0, $level->reserved - $reservation->quantity);
            $this->apply($level, MovementType::SaleOut, -$reservation->quantity, -$reservation->quantity, 'Venta',
                $orderReference ? 'order' : $reservation->reference_type, $orderReference ?? $reservation->reference_id);
            $reservation->update(['status' => 'consumed', 'closed_at' => now()]);

            return true;
        });
    }

    /** Libera las reservas vencidas (lo ejecuta el scheduler cada minuto). Devuelve cuántas liberó. */
    public function expireDue(int $limit = 500): int
    {
        $released = 0;
        StockReservation::query()->where('status', 'active')->where('expires_at', '<=', now())
            ->orderBy('expires_at')->limit($limit)->get()
            ->each(function (StockReservation $r) use (&$released) {
                $released += (int) $this->release($r, 'expired');
            });

        return $released;
    }

    public function reservationMinutes(): int
    {
        return max(1, (int) $this->settings->get('inventory', 'reservation_minutes', self::DEFAULT_RESERVATION_MINUTES));
    }

    // ---------------------------------------------------------------- Consultas

    /** Unidades disponibles para vender (null = no controla stock, sin límite). */
    public function available(ProductVariant $variant): ?int
    {
        $item = $variant->relationLoaded('inventoryItem') ? $variant->inventoryItem : $variant->inventoryItem()->first();
        if ($item && ! $item->track_stock) {
            return null;
        }
        $levels = $variant->relationLoaded('levels') ? $variant->levels : $variant->levels()->with('warehouse')->get();

        return (int) $levels->filter(fn ($l) => ($l->warehouse?->status ?? 'active') === 'active')
            ->sum(fn ($l) => max(0, $l->on_hand - $l->reserved));
    }

    // ---------------------------------------------------------------- Internos

    private function lockLevel(ProductVariant|int $variant, Warehouse|int $warehouse): InventoryLevel
    {
        $variantId = $variant instanceof ProductVariant ? $variant->id : $variant;
        $warehouseId = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;

        InventoryLevel::query()->firstOrCreate(['variant_id' => $variantId, 'warehouse_id' => $warehouseId]);

        return InventoryLevel::query()->where(['variant_id' => $variantId, 'warehouse_id' => $warehouseId])
            ->lockForUpdate()->firstOrFail();
    }

    private function apply(
        InventoryLevel $level,
        MovementType $type,
        int $delta,
        int $reservedChange,
        ?string $reason,
        ?string $referenceType,
        ?string $referenceId,
        ?int $reverses = null,
        ?int $transferId = null,
    ): StockMovement {
        $before = $level->on_hand;
        $level->on_hand = $before + $delta;
        $level->save();

        return StockMovement::query()->create([
            'variant_id' => $level->variant_id,
            'warehouse_id' => $level->warehouse_id,
            'type' => $type,
            'quantity' => $delta,
            'before_on_hand' => $before,
            'after_on_hand' => $level->on_hand,
            'reserved_change' => $reservedChange,
            'reason' => $reason ? mb_substr($reason, 0, 255) : null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => Auth::id(),
            'reverses_movement_id' => $reverses,
            'stock_transfer_id' => $transferId,
        ]);
    }

    /** Movimiento sin cambio de stock físico (reservas y liberaciones), para la trazabilidad. */
    private function log(InventoryLevel $level, MovementType $type, int $reservedChange, ?string $referenceType, ?string $referenceId, ?string $reason = null): void
    {
        StockMovement::query()->create([
            'variant_id' => $level->variant_id,
            'warehouse_id' => $level->warehouse_id,
            'type' => $type,
            'quantity' => 0,
            'before_on_hand' => $level->on_hand,
            'after_on_hand' => $level->on_hand,
            'reserved_change' => $reservedChange,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'user_id' => Auth::id(),
        ]);
    }

    private function audit(StockMovement $movement): void
    {
        app(AuditLogger::class)->record('StockMovement.created', $movement, ['on_hand' => $movement->before_on_hand], [
            'type' => $movement->type->value, 'on_hand' => $movement->after_on_hand, 'reason' => $movement->reason,
        ]);
    }
}
