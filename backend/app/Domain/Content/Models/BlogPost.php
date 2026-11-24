<?php

namespace App\Domain\Content\Models;

use App\Domain\Catalog\Models\Media;
use App\Domain\Content\Concerns\RedirectsOnSlugChange;
use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogPost extends Model
{
    use Auditable, RedirectsOnSlugChange;

    public const STATUSES = ['draft' => 'Borrador', 'published' => 'Publicado'];

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'category_id', 'featured_media_id', 'author_id', 'author_name',
        'status', 'published_at', 'seo_title', 'seo_description',
    ];

    protected static function booted(): void
    {
        // La tienda guarda el blog en caché: se actualiza al guardar o eliminar
        static::saved(fn () => RevalidateStorefront::dispatch(['blog']));
        static::deleted(fn () => RevalidateStorefront::dispatch(['blog']));
    }

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    /** Publicado y con fecha de publicación cumplida (una fecha futura = programado). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isVisible(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function storefrontPath(): string
    {
        return '/blog/'.$this->slug;
    }
}
