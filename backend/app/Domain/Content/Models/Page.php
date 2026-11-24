<?php

namespace App\Domain\Content\Models;

use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Página del constructor: portada, landing o institucional. Su contenido vive en versiones inmutables. */
class Page extends Model
{
    use Auditable, HasUuids;

    public const TYPES = [
        'home' => 'Portada',
        'landing' => 'Landing (campaña)',
        'institutional' => 'Institucional',
    ];

    public const STATUSES = [
        'draft' => 'Borrador',
        'published' => 'Publicada',
    ];

    /** Rutas de la tienda que una página no puede usar. */
    public const RESERVED_SLUGS = [
        'api', 'media', 'catalogo', 'categoria', 'marca', 'producto', 'buscar', 'carrito', 'checkout', 'cuenta',
        'pago', 'pedido', 'seguimiento', 'vista-previa', 'blog', 'admin', 'storage', 'favicon.ico', 'robots.txt', 'sitemap.xml',
    ];

    protected $fillable = ['title', 'slug', 'type', 'status', 'seo_title', 'seo_description', 'published_version_id', 'published_at', 'updated_by'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderByDesc('number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(PageVersion::class)->ofMany('number', 'max');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(PageVersion::class, 'published_version_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_version_id');
    }

    /** Hay cambios guardados que aún no se publican. */
    public function hasUnpublishedChanges(): bool
    {
        $latest = $this->latestVersion;

        return $latest !== null && $latest->id !== $this->published_version_id;
    }

    /** Ruta en la tienda. */
    public function storefrontPath(): string
    {
        return $this->type === 'home' ? '/' : '/'.$this->slug;
    }
}
