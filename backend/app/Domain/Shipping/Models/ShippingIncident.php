<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Problema con un despacho: atraso, daño, pérdida, dirección incorrecta, etc. Solo lo ve el equipo. */
class ShippingIncident extends Model
{
    use Auditable;

    public const TYPES = [
        'delay' => 'Atraso',
        'damaged' => 'Producto dañado',
        'lost' => 'Extraviado',
        'wrong_address' => 'Dirección incorrecta',
        'failed_delivery' => 'No se pudo entregar',
        'other' => 'Otro',
    ];

    public const STATUSES = ['open' => 'Abierta', 'in_progress' => 'En gestión', 'resolved' => 'Resuelta'];

    protected $fillable = ['shipment_id', 'order_id', 'type', 'status', 'description', 'resolution', 'owner_user_id', 'created_by', 'resolved_at'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
