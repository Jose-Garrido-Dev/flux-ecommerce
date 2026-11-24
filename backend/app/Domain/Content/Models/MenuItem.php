<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    public const LINK_TYPES = [
        'catalog' => 'Todo el catálogo',
        'category' => 'Categoría',
        'brand' => 'Marca',
        'page' => 'Página',
        'url' => 'Dirección (URL)',
    ];

    protected $fillable = ['menu_id', 'parent_id', 'label', 'link_type', 'link_target', 'position'];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }
}
