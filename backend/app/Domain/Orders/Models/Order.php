<?php

namespace App\Domain\Orders\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Payments\Models\Refund;
use App\Domain\Shipping\Models\Shipment;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Pedido. Nunca se borra; sus líneas son una copia fija del catálogo al momento de comprar. */
class Order extends Model
{
    use HasUuids;

    public const STATUSES = ['pending' => 'Pendiente de pago', 'processing' => 'En preparación', 'completed' => 'Completado', 'cancelled' => 'Cancelado'];

    public const PAYMENT_STATUSES = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'expired' => 'Vencido', 'cancelled' => 'Anulado', 'refunded' => 'Reembolsado', 'partially_refunded' => 'Reembolso parcial'];

    public const FULFILLMENT_STATUSES = [
        'unfulfilled' => 'Por preparar', 'preparing' => 'En preparación', 'partially_shipped' => 'Despachado en parte',
        'shipped' => 'Despachado', 'ready_for_pickup' => 'Listo para retiro', 'delivered' => 'Entregado', 'picked_up' => 'Retirado',
    ];

    public const DOCUMENT_TYPES = ['boleta' => 'Boleta', 'factura' => 'Factura'];

    /** Desde aquí parte el número visible del pedido (no revela cuántos pedidos tiene la tienda desde el primero). */
    public const NUMBER_OFFSET = 1000;

    protected $fillable = [
        'idempotency_key', 'customer_id', 'cart_id', 'email', 'phone', 'first_name', 'last_name', 'channel', 'currency',
        'status', 'payment_status', 'fulfillment_status', 'subtotal', 'discount_total', 'tax_total', 'shipping_total',
        'grand_total', 'paid_total', 'refunded_total', 'shipping_address', 'billing_address', 'document_type',
        'billing_rut', 'billing_name', 'billing_activity', 'notes', 'tracking_token_hash', 'placed_at', 'paid_at', 'cancelled_at',
    ];

    protected $attributes = ['status' => 'pending', 'payment_status' => 'pending', 'fulfillment_status' => 'unfulfilled', 'channel' => 'web', 'currency' => 'CLP'];

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Los pedidos no se eliminan: se cancelan.'));
        static::created(function (Order $order) {
            $order->forceFill(['number' => (string) (self::NUMBER_OFFSET + $order->id)])->saveQuietly();
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            ...array_fill_keys(['subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total', 'paid_total', 'refunded_total'], 'integer'),
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function shippingLine(): HasOne
    {
        return $this->hasOne(OrderShippingLine::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function isCancellable(): bool
    {
        return $this->status === 'pending' && $this->payment_status !== 'paid';
    }
}
