<?php

namespace App\Domain\Catalog\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use Auditable;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'status', 'is_default', 'position',
        'weight_g', 'length_mm', 'width_mm', 'height_mm', 'image_media_id',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'position' => 'integer',
            'weight_g' => 'integer',
            'length_mm' => 'integer',
            'width_mm' => 'integer',
            'height_mm' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VariantPrice::class, 'variant_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values', 'variant_id')
            ->withPivot('attribute_id');
    }

    public function priceIn(string $currency = 'CLP'): ?VariantPrice
    {
        return $this->prices->firstWhere('currency', $currency);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
