<?php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tarifa de un método para una zona y un rango de peso (ambos límites incluidos). */
class ShippingRate extends Model
{
    protected $fillable = ['method_id', 'zone_id', 'min_weight_g', 'max_weight_g', 'amount', 'estimated_days', 'position'];

    protected $attributes = ['min_weight_g' => 0, 'position' => 0];

    protected function casts(): array
    {
        return ['min_weight_g' => 'integer', 'max_weight_g' => 'integer', 'amount' => 'integer', 'position' => 'integer', 'zone_id' => 'integer'];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'method_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'zone_id');
    }

    public function covers(?int $zoneId, int $weightG): bool
    {
        return ($this->zone_id === null || $this->zone_id === $zoneId)
            && $weightG >= $this->min_weight_g
            && ($this->max_weight_g === null || $weightG <= $this->max_weight_g);
    }
}
