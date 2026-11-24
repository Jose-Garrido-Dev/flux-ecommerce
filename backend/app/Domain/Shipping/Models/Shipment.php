<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Despacho (o retiro) de todo o parte de un pedido. */
class Shipment extends Model
{
    use Auditable, HasUuids;

    public const STATUSES = [
        'preparing' => 'En preparación',
        'ready_for_pickup' => 'Listo para retiro',
        'shipped' => 'Despachado',
        'in_transit' => 'En camino',
        'out_for_delivery' => 'En reparto',
        'failed_attempt' => 'Intento de entrega fallido',
        'delivered' => 'Entregado',
        'picked_up' => 'Retirado',
        'returned' => 'Devuelto a la tienda',
        'cancelled' => 'Anulado',
    ];

    /** Transiciones permitidas. Estados finales: delivered, picked_up, returned, cancelled. */
    public const TRANSITIONS = [
        'preparing' => ['shipped', 'ready_for_pickup', 'cancelled'],
        'ready_for_pickup' => ['picked_up', 'cancelled'],
        'shipped' => ['in_transit', 'out_for_delivery', 'failed_attempt', 'delivered', 'returned'],
        'in_transit' => ['in_transit', 'out_for_delivery', 'failed_attempt', 'delivered', 'returned'],
        'out_for_delivery' => ['failed_attempt', 'delivered', 'returned'],
        'failed_attempt' => ['in_transit', 'out_for_delivery', 'delivered', 'returned'],
        'delivered' => [],
        'picked_up' => [],
        'returned' => [],
        'cancelled' => [],
    ];

    public const FINAL = ['delivered', 'picked_up', 'returned', 'cancelled'];

    protected $fillable = [
        'uuid', 'order_id', 'tracking_token_hash', 'carrier_code', 'carrier_name', 'tracking_number', 'tracking_url', 'status',
        'packages', 'weight_g', 'cost_amount', 'notes', 'created_by', 'shipped_at', 'delivered_at',
    ];

    protected $hidden = ['tracking_token_hash'];

    protected function casts(): array
    {
        return [
            'packages' => 'integer', 'weight_g' => 'integer', 'cost_amount' => 'integer',
            'shipped_at' => 'datetime', 'delivered_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(ShippingIncident::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }
}
