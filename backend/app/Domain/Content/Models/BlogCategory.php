<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\RedirectsOnSlugChange;
use App\Domain\Content\Jobs\RevalidateStorefront;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogCategory extends Model
{
    use RedirectsOnSlugChange;

    protected $fillable = ['name', 'slug', 'position'];

    protected static function booted(): void
    {
        // La tienda guarda el blog en caché: se actualiza al guardar o eliminar
        static::saved(fn () => RevalidateStorefront::dispatch(['blog']));
        static::deleted(fn () => RevalidateStorefront::dispatch(['blog']));
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }

    public function storefrontPath(): string
    {
        return '/blog?categoria='.$this->slug;
    }

    /** Las categorías del blog se filtran por parámetro: no generan redirecciones. */
    protected function redirectsEnabled(): bool
    {
        return false;
    }
}
