<?php

namespace App\Domain\Marketing\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Coupon extends Model
{
    use Auditable;

    public const TYPES = ['percent' => 'Porcentaje', 'fixed' => 'Monto fijo', 'free_shipping' => 'Envío gratis'];

    public const STATUSES = ['active' => 'Activo', 'inactive' => 'Inactivo'];

    protected $fillable = [
        'code', 'name', 'type', 'value', 'min_subtotal', 'max_discount', 'usage_limit', 'per_customer_limit',
        'starts_at', 'ends_at', 'stackable', 'first_purchase_only', 'status',
    ];

    protected $attributes = ['status' => 'active', 'stackable' => true, 'first_purchase_only' => false, 'value' => 0];

    protected function casts(): array
    {
        return [
            'value' => 'integer', 'min_subtotal' => 'integer', 'max_discount' => 'integer', 'usage_limit' => 'integer',
            'per_customer_limit' => 'integer', 'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'stackable' => 'boolean', 'first_purchase_only' => 'boolean',
        ];
    }

    public static function normalize(?string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $code));
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalize($value);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(CouponTarget::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /** Canjes de pedidos que no se cancelaron (un pedido cancelado libera el uso). */
    public function activeRedemptions()
    {
        return $this->redemptions()->whereHas('order', fn ($query) => $query->where('status', '!=', 'cancelled'));
    }

    public function describe(): string
    {
        return match ($this->type) {
            'percent' => $this->value.'% de descuento',
            'fixed' => '$'.number_format($this->value, 0, ',', '.').' de descuento',
            default => 'Envío gratis',
        };
    }
}
