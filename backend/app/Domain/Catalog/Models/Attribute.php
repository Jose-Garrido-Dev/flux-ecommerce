<?php

namespace App\Domain\Catalog\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Característica de producto (color, talla, material...). Puede ser filtro y/o eje de variantes. */
class Attribute extends Model
{
    use Auditable;

    public const TYPES = [
        'select' => 'Selector', 'color' => 'Color', 'text' => 'Texto', 'number' => 'Número',
        'image' => 'Imagen', 'measure' => 'Medida',
    ];

    public const DISPLAYS = ['pill' => 'Botones', 'swatch' => 'Muestras de color', 'select' => 'Lista desplegable'];

    protected $fillable = [
        'code', 'name', 'type', 'unit', 'display', 'is_filterable', 'is_variant_axis', 'visible_in_catalog',
        'is_required', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_filterable' => 'boolean',
            'is_variant_axis' => 'boolean',
            'visible_in_catalog' => 'boolean',
            'is_required' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('position');
    }
}
