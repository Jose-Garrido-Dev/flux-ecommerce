<?php

namespace App\Domain\Content\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Menú de la tienda en una ubicación fija (encabezado o pie). */
class Menu extends Model
{
    use Auditable;

    public const LOCATIONS = [
        'header' => 'Menú principal (encabezado)',
        'footer' => 'Pie de página',
    ];

    protected $fillable = ['location', 'name'];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('position');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(MenuItem::class)->whereNull('parent_id')->orderBy('position');
    }

    public static function for(string $location): self
    {
        return self::query()->firstOrCreate(['location' => $location], ['name' => self::LOCATIONS[$location] ?? $location]);
    }
}
