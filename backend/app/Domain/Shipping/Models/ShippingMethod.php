<?php

namespace App\Domain\Shipping\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Método de envío simple (Fase 5): tarifa fija por región o retiro en tienda.
 * Las tarifas por zona, peso y courier llegan en la Fase 7.
 */
class ShippingMethod extends Model
{
    use Auditable;

    public const TYPES = ['flat' => 'Despacho a domicilio (tarifa fija)', 'pickup' => 'Retiro en tienda'];

    public const STATUSES = ['active' => 'Activo', 'inactive' => 'Inactivo'];

    protected $fillable = ['code', 'name', 'type', 'description', 'amount', 'free_over', 'region_ids', 'estimated_days', 'status', 'position'];

    protected $attributes = ['status' => 'active', 'amount' => 0, 'position' => 0];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'free_over' => 'integer', 'region_ids' => 'array', 'position' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function servesRegion(?int $regionId): bool
    {
        if ($this->type === 'pickup') {
            return true;
        }

        return empty($this->region_ids) || ($regionId !== null && in_array($regionId, array_map('intval', $this->region_ids), true));
    }

    /** Costo con IVA para un subtotal dado. */
    public function costFor(int $subtotal): int
    {
        if ($this->free_over !== null && $subtotal >= $this->free_over) {
            return 0;
        }

        return $this->amount;
    }
}
