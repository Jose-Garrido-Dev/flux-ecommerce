<?php

namespace App\Domain\Catalog\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Categoría en árbol. `path` guarda los ids de los ancestros ("/1/4/") para
 * consultar descendientes sin recursión. Los ciclos se rechazan al guardar.
 */
class Category extends Model
{
    use Auditable;

    public const MAX_DEPTH = 4;

    public const STATUSES = ['active' => 'Activa', 'inactive' => 'Desactivada'];

    protected $fillable = [
        'parent_id', 'name', 'slug', 'description', 'image_media_id', 'banner_media_id', 'icon', 'color',
        'position', 'status', 'in_menu', 'on_home', 'seo_title', 'seo_description',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            $category->slug ??= Str::slug($category->name);

            if (! $category->isDirty('parent_id') && $category->exists) {
                return;
            }

            $parent = $category->parent_id ? static::query()->findOrFail($category->parent_id) : null;

            if ($parent && $category->exists) {
                $ownPrefix = $category->getOriginal('path').$category->id.'/';
                if ($parent->id === $category->id || str_starts_with($parent->path.$parent->id.'/', $ownPrefix)) {
                    throw new InvalidArgumentException('Una categoría no puede quedar dentro de sí misma ni de sus subcategorías.');
                }
            }

            $category->path = $parent ? $parent->path.$parent->id.'/' : '/';
            $category->depth = $parent ? $parent->depth + 1 : 0;

            if ($category->depth >= self::MAX_DEPTH) {
                throw new InvalidArgumentException('Se permiten como máximo '.self::MAX_DEPTH.' niveles de categorías.');
            }
        });

        // Al mover una categoría, sus descendientes heredan la nueva ruta
        static::updated(function (Category $category) {
            if (! $category->wasChanged('path')) {
                return;
            }
            $old = $category->getOriginal('path').$category->id.'/';
            $new = $category->path.$category->id.'/';
            $delta = $category->depth - $category->getOriginal('depth');

            DB::update(
                'UPDATE categories SET path = ? || substr(path, ?), depth = depth + ? WHERE path LIKE ?',
                [$new, strlen($old) + 1, $delta, $old.'%'],
            );
        });
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'depth' => 'integer',
            'in_menu' => 'boolean',
            'on_home' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_media_id');
    }

    /** "Ropa › Poleras": nombre con sus ancestros, para listados y selectores. */
    public function fullName(): string
    {
        return $this->ancestors()->push($this)->pluck('name')->implode(' › ');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('position');
    }

    /** Ids de esta categoría y todas sus subcategorías. */
    public function selfAndDescendantIds(): array
    {
        return static::query()
            ->where('id', $this->id)
            ->orWhere('path', 'like', $this->path.$this->id.'/%')
            ->pluck('id')->all();
    }

    /** Ancestros desde la raíz (para migas de pan). */
    public function ancestors(): Collection
    {
        $ids = array_filter(explode('/', $this->path));
        if ($ids === []) {
            return collect();
        }
        $byId = static::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $byId[(int) $id] ?? null)->filter()->values();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
