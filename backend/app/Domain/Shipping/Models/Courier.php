<?php

namespace App\Domain\Shipping\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Courier con el que trabaja la tienda. Sin credenciales verificadas, el número de seguimiento
 * se ingresa a mano y el cliente lo consulta en la página pública del courier.
 */
class Courier extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'enabled', 'tracking_url_template', 'credentials', 'verified_capabilities', 'verified_at', 'last_error'];

    protected $hidden = ['credentials'];

    public array $auditExclude = ['credentials'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'verified_capabilities' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    public static function for(?string $code): ?self
    {
        return $code ? static::query()->where('code', $code)->first() : null;
    }

    public function trackingUrl(?string $number): ?string
    {
        if (! filled($number) || ! filled($this->tracking_url_template) || ! str_contains($this->tracking_url_template, '{numero}')) {
            return null;
        }

        return str_replace('{numero}', rawurlencode((string) $number), $this->tracking_url_template);
    }

    public function isVerified(string $capability): bool
    {
        return $this->verified_at !== null && in_array($capability, $this->verified_capabilities ?? [], true);
    }
}
