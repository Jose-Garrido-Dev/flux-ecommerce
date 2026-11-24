<?php

namespace App\Domain\Returns\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Devolución o cambio de productos de un pedido (tabla returns; "Return" es palabra reservada en PHP).
 * Flujo: solicitada → en revisión → aprobada (esperando el producto) o rechazada → recibida → resuelta → cerrada.
 */
class ReturnRequest extends Model
{
    use Auditable, HasUuids;

    protected $table = 'returns';

    public const TYPES = ['return' => 'Devolución con reembolso', 'exchange' => 'Cambio por otro producto'];

    public const REASONS = [
        'retracto' => 'Me arrepentí de la compra',
        'defective' => 'El producto tiene una falla',
        'damaged' => 'Llegó dañado',
        'wrong_item' => 'Llegó un producto distinto',
        'size' => 'Talla o medida incorrecta',
        'other' => 'Otro motivo',
    ];

    public const STATUSES = [
        'requested' => 'Solicitada',
        'in_review' => 'En revisión',
        'approved' => 'Aprobada: esperando el producto',
        'rejected' => 'Rechazada',
        'received' => 'Producto recibido',
        'processed' => 'Resuelta',
        'closed' => 'Cerrada',
        'cancelled' => 'Anulada',
    ];

    public const TRANSITIONS = [
        'requested' => ['in_review', 'approved', 'rejected', 'cancelled'],
        'in_review' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['received', 'cancelled'],
        'received' => ['processed'],
        'processed' => ['closed'],
        'rejected' => ['closed'],
        'closed' => [],
        'cancelled' => [],
    ];

    /** Estados en que la devolución sigue "abierta" y ocupa las unidades del pedido. */
    public const ACTIVE = ['requested', 'in_review', 'approved', 'received', 'processed', 'closed'];

    public const CONDITIONS = ['new' => 'Sin uso, con embalaje', 'opened' => 'Abierto o usado, en buen estado', 'damaged' => 'Dañado o con falla'];

    protected $fillable = [
        'uuid', 'order_id', 'customer_id', 'type', 'reason', 'customer_notes', 'status', 'rejection_reason', 'instructions',
        'resolution', 'refund_amount', 'owner_user_id', 'approved_by', 'approved_at', 'received_at', 'processed_at', 'closed_at',
    ];

    protected $attributes = ['status' => 'requested', 'type' => 'return'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['approved_at' => 'datetime', 'received_at' => 'datetime', 'processed_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ReturnEvidence::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }

    /** Número visible: pedido y correlativo, p. ej. "1001-D1". */
    public function label(): string
    {
        $n = static::query()->where('order_id', $this->order_id)->where('id', '<=', $this->id)->count();
        $number = $this->relationLoaded('order') ? $this->order?->number : Order::query()->whereKey($this->order_id)->value('number');

        return ($number ?? '?').'-D'.$n;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** Monto sugerido: precio pagado por las unidades devueltas. */
    public function itemsTotal(): int
    {
        $this->loadMissing('items.orderItem');

        return (int) $this->items->sum(fn (ReturnItem $i) => $i->orderItem?->paidFor($i->quantity) ?? 0);
    }
}
