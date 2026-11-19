<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Pricing\Models\TaxClass;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use Auditable, HasUuids;

    protected $fillable = [
        'name', 'slug', 'short_description', 'description', 'brand_id', 'primary_category_id',
        'tax_class_id', 'type', 'status', 'featured', 'has_variants', 'published_at', 'tags', 'video_url',
        'is_fragile', 'special_packaging', 'requires_cold_chain', 'allows_pickup', 'shipping_notes',
        'seo_title', 'seo_description', 'canonical_url',
    ];

    public const RELATION_TYPES = [
        'related' => 'Relacionados',
        'complementary' => 'Complementarios',
        'alternative' => 'Alternativos',
        'cross_sell' => 'Venta cruzada',
        'recommended' => 'Recomendados',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'featured' => 'boolean',
            'has_variants' => 'boolean',
            'published_at' => 'datetime',
            'tags' => 'array',
            'is_fragile' => 'boolean',
            'special_packaging' => 'boolean',
            'requires_cold_chain' => 'boolean',
            'allows_pickup' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withPivot('position');
    }

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media')
            ->withPivot(['position', 'is_primary', 'variant_id'])
            ->orderByPivot('is_primary', 'desc')
            ->orderByPivot('position');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    /** Imagen principal (catálogo). */
    public function primaryMedia(): ?Media
    {
        return $this->media->first();
    }

    public function relations(): HasMany
    {
        return $this->hasMany(ProductRelation::class)->orderBy('position');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    /** Visible en la tienda: publicado, con fecha de publicación cumplida. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('variants', fn (Builder $v) => $v->active());
    }

    public function isVisible(): bool
    {
        return $this->status === ProductStatus::Active
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }
}
