<?php

namespace App\Domain\Shipping\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Hito del seguimiento. Un evento externo (courier) con el mismo identificador no se registra dos veces. */
class ShipmentEvent extends Model
{
    public const UPDATED_AT = null;

    public const SOURCES = ['admin' => 'Equipo de la tienda', 'courier' => 'Courier', 'system' => 'Sistema'];

    protected $fillable = ['shipment_id', 'status', 'description', 'location', 'source', 'external_event_id', 'user_id', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
