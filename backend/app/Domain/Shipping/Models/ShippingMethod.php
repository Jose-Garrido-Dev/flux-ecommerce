<?php

namespace App\Domain\Shipping\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Método de envío:
 * - flat: tarifa fija (opcionalmente solo en algunas regiones).
 * - table: tarifas por zona y rango de peso (con peso volumétrico opcional).
 * - pickup: retiro en tienda.
 * Con courier asignado es un despacho por courier; sin courier, despacho propio.
 */
class ShippingMethod extends Model
{
    use Auditable;

    public const TYPES = [
        'flat' => 'Tarifa fija',
        'table' => 'Tarifas por zona y peso',
        'pickup' => 'Retiro en tienda',
    ];

    public const STATUSES = ['active' => 'Activo', 'inactive' => 'Inactivo'];

    protected $fillable = [
        'code', 'name', 'type', 'carrier_code', 'description', 'amount', 'free_over', 'volumetric_factor', 'max_weight_g',
        'supports_cold_chain', 'pickup_address', 'pickup_hours', 'pickup_instructions', 'region_ids', 'estimated_days', 'status', 'position',
    ];

    protected $attributes = ['status' => 'active', 'amount' => 0, 'position' => 0];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', 'free_over' => 'integer', 'region_ids' => 'array', 'position' => 'integer',
            'volumetric_factor' => 'integer', 'max_weight_g' => 'integer', 'supports_cold_chain' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'method_id')->orderBy('position')->orderBy('min_weight_g');
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'carrier_code', 'code');
    }

    /** Solo para tarifa fija: sin regiones elegidas despacha a todo Chile. */
    public function servesRegion(?int $regionId): bool
    {
        if ($this->type === 'pickup') {
            return true;
        }

        return empty($this->region_ids) || ($regionId !== null && in_array($regionId, array_map('intval', $this->region_ids), true));
    }

    public function isFreeFor(int $subtotal): bool
    {
        return $this->free_over !== null && $subtotal >= $this->free_over;
    }

    public function deliveryLabel(): string
    {
        return match (true) {
            $this->type === 'pickup' => 'Retiro en tienda',
            filled($this->carrier_code) => 'Courier: '.($this->courier?->name ?? $this->carrier_code),
            default => 'Despacho propio',
        };
    }
}
