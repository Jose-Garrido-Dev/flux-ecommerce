<?php

namespace App\Domain\Content\Models;

use App\Domain\Catalog\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Carpeta lógica de la biblioteca multimedia (no cambia dónde se guarda el archivo). */
class MediaFolder extends Model
{
    protected $fillable = ['name', 'parent_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /** "Campañas / Verano" */
    public function path(): string
    {
        $names = [$this->name];
        $parent = $this->parent;
        $guard = 0;
        while ($parent && $guard++ < 10) {
            array_unshift($names, $parent->name);
            $parent = $parent->parent;
        }

        return implode(' / ', $names);
    }
}
