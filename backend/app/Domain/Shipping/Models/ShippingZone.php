<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Shipping\Services\CommuneName;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona de envío: regiones completas, comunas sueltas y comunas excluidas.
 * Si una comuna cae en varias zonas, gana la de menor prioridad.
 */
class ShippingZone extends Model
{
    use Auditable;

    protected $fillable = ['name', 'region_ids', 'communes', 'excluded_communes', 'priority', 'status'];

    protected $attributes = ['status' => 'active', 'priority' => 100];

    protected function casts(): array
    {
        return ['region_ids' => 'array', 'communes' => 'array', 'excluded_communes' => 'array', 'priority' => 'integer'];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class, 'zone_id');
    }

    public function contains(?int $regionId, ?string $commune): bool
    {
        $name = CommuneName::normalize($commune);
        $listed = array_map(CommuneName::normalize(...), $this->communes ?? []);
        $excluded = array_map(CommuneName::normalize(...), $this->excluded_communes ?? []);
        if ($name !== '' && in_array($name, $listed, true)) {
            return true;
        }
        $inRegion = $regionId !== null && in_array($regionId, array_map('intval', $this->region_ids ?? []), true);

        return $inRegion && ! ($name !== '' && in_array($name, $excluded, true));
    }

    /** Zona que corresponde a una dirección, o null si ninguna la cubre. */
    public static function match(?int $regionId, ?string $commune): ?self
    {
        return static::query()->active()->orderBy('priority')->orderBy('id')->get()
            ->first(fn (self $z) => $z->contains($regionId, $commune));
    }
}
