<?php

namespace App\Domain\Returns\Services;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderEvents;
use App\Domain\Payments\Services\RefundService;
use App\Domain\Returns\Mail\ReturnMail;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Devoluciones y cambios: solicitud (cliente o tienda), revisión, aprobación con instrucciones o rechazo
 * con motivo, recepción con condición de cada producto (vuelve al stock solo si está en condiciones),
 * y resolución con reembolso o cambio. Toda transición no permitida responde 422.
 */
class ReturnService
{
    public function __construct(
        private readonly ReturnSettings $settings,
        private readonly InventoryService $inventory,
        private readonly RefundService $refunds,
    ) {}

    /** Unidades que aún se pueden devolver por ítem del pedido. @return array<int, int> order_item_id => cantidad */
    public function returnable(Order $order): array
    {
        $order->loadMissing('items');
        $taken = ReturnItem::query()
            ->whereHas('returnRequest', fn ($q) => $q->where('order_id', $order->id)->whereIn('status', ReturnRequest::ACTIVE))
            ->selectRaw('order_item_id, sum(quantity) as qty')->groupBy('order_item_id')->pluck('qty', 'order_item_id');

        $left = [];
        foreach ($order->items as $item) {
            $left[$item->id] = max(0, $item->quantity - (int) ($taken[$item->id] ?? 0));
        }

        return $left;
    }

    /** Fecha desde la que corre el plazo: la última entrega o retiro. */
    public function deliveredAt(Order $order): ?Carbon
    {
        $at = Shipment::query()->where('order_id', $order->id)->whereIn('status', ['delivered', 'picked_up'])->max('delivered_at');

        return $at ? Carbon::parse($at) : null;
    }

    /** Fecha límite para pedir devolución, o null si el pedido aún no se entrega. */
    public function deadline(Order $order): ?Carbon
    {
        return $this->deliveredAt($order)?->copy()->addDays($this->settings->days())->endOfDay();
    }

    /** Motivo por el que el cliente no puede pedir devolución, o null si puede. */
    public function customerBlocker(Order $order): ?string
    {
        return match (true) {
            ! $this->settings->enabled() => 'La tienda no recibe solicitudes de devolución en línea. Escríbenos para coordinarla.',
            ! in_array($order->fulfillment_status, ['delivered', 'picked_up'], true) && $order->status !== 'completed' => 'Podrás pedir una devolución cuando recibas tu pedido.',
            $order->payment_status === 'refunded' => 'Este pedido ya fue reembolsado completo.',
            ($deadline = $this->deadline($order)) !== null && now()->gt($deadline) => 'El plazo para pedir una devolución venció el '.$deadline->format('d-m-Y').'.',
            array_sum($this->returnable($order)) === 0 => 'Ya pediste la devolución de todos los productos de este pedido.',
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $items  order_item_id => cantidad
     * @param  string  $actor  customer (desde la tienda) o user (desde el panel, sin plazo)
     */
    public function request(Order $order, array $items, string $type, string $reason, ?string $notes = null, string $actor = 'customer'): ReturnRequest
    {
        if ($actor === 'customer' && ($blocker = $this->customerBlocker($order))) {
            throw ValidationException::withMessages(['items' => $blocker]);
        }
        if ($actor !== 'customer' && ! in_array($order->status, ['processing', 'completed'], true)) {
            throw ValidationException::withMessages(['items' => 'Solo se registran devoluciones de pedidos pagados.']);
        }
        if (! array_key_exists($type, ReturnRequest::TYPES) || ($type === 'exchange' && ! $this->settings->allowExchange())) {
            throw ValidationException::withMessages(['type' => 'Tipo de solicitud no válido.']);
        }
        if (! array_key_exists($reason, ReturnRequest::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Elige un motivo.']);
        }

        $return = DB::transaction(function () use ($order, $items, $type, $reason, $notes, $actor) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $left = $this->returnable($order);
            $items = array_filter(array_map('intval', $items), fn ($q) => $q > 0);
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'Elige al menos un producto para devolver.']);
            }
            foreach ($items as $id => $qty) {
                if (! array_key_exists((int) $id, $left) || $qty > $left[(int) $id]) {
                    throw ValidationException::withMessages(['items' => 'La cantidad supera lo que se puede devolver de ese producto.']);
                }
            }
            $return = ReturnRequest::query()->create([
                'order_id' => $order->id, 'customer_id' => $order->customer_id, 'type' => $type, 'reason' => $reason,
                'customer_notes' => $notes ?: null, 'instructions' => $this->settings->instructions(),
            ]);
            foreach ($items as $id => $qty) {
                $return->items()->create(['order_item_id' => (int) $id, 'quantity' => $qty]);
            }
            OrderEvents::record($order, 'return_requested', ['return' => $return->uuid, 'label' => $return->label()], visible: true, actor: $actor === 'customer' ? 'customer' : null);

            return $return;
        });

        Mail::to($order->email)->queue(new ReturnMail($order, $return, 'requested'));
        if ($actor === 'customer' && ($notify = app(SettingsRepository::class)->get('business', 'contact_email'))) {
            Mail::to($notify)->queue(new ReturnMail($order, $return, 'new_admin'));
        }

        return $return;
    }

    public function startReview(ReturnRequest $return, ?int $ownerId = null): ReturnRequest
    {
        return $this->move($return, 'in_review', ['owner_user_id' => $ownerId ?? Auth::id()]);
    }

    public function approve(ReturnRequest $return, ?string $instructions): ReturnRequest
    {
        if (blank($instructions)) {
            throw ValidationException::withMessages(['instructions' => 'Indica cómo y dónde debe enviar el producto el cliente.']);
        }
        $return = $this->move($return, 'approved', ['instructions' => $instructions, 'approved_by' => Auth::id(), 'approved_at' => now()]);
        Mail::to($return->order->email)->queue(new ReturnMail($return->order, $return, 'approved'));

        return $return;
    }

    public function reject(ReturnRequest $return, ?string $reason): ReturnRequest
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['rejection_reason' => 'El rechazo necesita un motivo: el cliente lo recibe por correo.']);
        }
        $return = $this->move($return, 'rejected', ['rejection_reason' => $reason]);
        Mail::to($return->order->email)->queue(new ReturnMail($return->order, $return, 'rejected'));

        return $return;
    }

    /** El cliente anula su solicitud antes de enviar el producto. */
    public function cancel(ReturnRequest $return, string $actor = 'customer'): ReturnRequest
    {
        return $this->move($return, 'cancelled', [], $actor);
    }

    /**
     * Recepción física. Por ítem: condición y si vuelve al stock. Un producto dañado no vuelve al stock.
     *
     * @param  array<int, array{condition: string, restock?: bool}>  $conditions  return_item_id => datos
     */
    public function receive(ReturnRequest $return, array $conditions): ReturnRequest
    {
        return DB::transaction(function () use ($return, $conditions) {
            $return = ReturnRequest::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertCan($return, 'received');
            $return->load('items.orderItem', 'order');
            $warehouse = Warehouse::default();
            foreach ($return->items as $item) {
                $data = $conditions[$item->id] ?? null;
                $condition = $data['condition'] ?? null;
                if (! array_key_exists((string) $condition, ReturnRequest::CONDITIONS)) {
                    throw ValidationException::withMessages(['conditions' => 'Indica en qué condición llegó cada producto.']);
                }
                $restock = (bool) ($data['restock'] ?? false);
                if ($restock && $condition === 'damaged') {
                    throw ValidationException::withMessages(['conditions' => 'Un producto dañado no vuelve al stock. Regístralo como merma si corresponde.']);
                }
                $item->update(['condition' => $condition, 'restock' => $restock]);
                $variantId = $item->orderItem?->variant_id;
                if ($restock && $variantId && $this->inventory->item($variantId)->track_stock) {
                    $this->inventory->move($variantId, $warehouse, MovementType::ReturnIn, $item->quantity,
                        'Devolución '.$return->label(), 'return', $return->uuid);
                }
            }

            return $this->move($return, 'received', ['received_at' => now()]);
        });
    }

    /**
     * Resolución: en una devolución se reembolsa (por defecto lo pagado por esas unidades);
     * en un cambio se describe qué se envió.
     */
    public function process(ReturnRequest $return, ?int $refundAmount, ?string $resolution): ReturnRequest
    {
        $this->assertCan($return, 'processed');
        if ($return->type === 'exchange') {
            if (blank($resolution)) {
                throw ValidationException::withMessages(['resolution' => 'Describe el cambio: qué producto se envía y cómo.']);
            }
            $return = $this->move($return, 'processed', ['resolution' => $resolution, 'processed_at' => now()]);
        } else {
            $amount = $refundAmount ?? $return->itemsTotal();
            if ($amount < 0) {
                throw ValidationException::withMessages(['refund_amount' => 'El monto no puede ser negativo.']);
            }
            if ($amount > 0) {
                $this->refunds->refund($return->order, $amount, 'Devolución '.$return->label(), restock: false, returnId: $return->id);
            }
            $text = $resolution ?: ($amount > 0 ? 'Hicimos un reembolso de $'.number_format($amount, 0, ',', '.').'.' : 'Sin reembolso.');
            $return = $this->move($return->fresh(), 'processed', ['refund_amount' => $amount, 'resolution' => $text, 'processed_at' => now()]);
        }
        Mail::to($return->order->email)->queue(new ReturnMail($return->order, $return, 'processed'));

        return $return;
    }

    public function close(ReturnRequest $return): ReturnRequest
    {
        return $this->move($return, 'closed', ['closed_at' => now()]);
    }

    public function assertCan(ReturnRequest $return, string $to): void
    {
        if (! $return->canMoveTo($to)) {
            throw ValidationException::withMessages(['status' => sprintf(
                'Una devolución «%s» no puede pasar a «%s».', $return->statusLabel(), ReturnRequest::STATUSES[$to] ?? $to,
            )]);
        }
    }

    private function move(ReturnRequest $return, string $to, array $changes = [], ?string $actor = null): ReturnRequest
    {
        return DB::transaction(function () use ($return, $to, $changes, $actor) {
            $return = ReturnRequest::query()->whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertCan($return, $to);
            $from = $return->status;
            $return->update(['status' => $to, ...$changes]);
            $return->load('order');
            OrderEvents::record($return->order, 'return_status', ['return' => $return->uuid, 'label' => $return->label()],
                visible: true, from: $from, to: $to, actor: $actor === 'customer' ? 'customer' : null);
            app(AuditLogger::class)->record('ReturnRequest.'.$to, $return, ['status' => $from], ['status' => $to]);

            return $return;
        });
    }
}
