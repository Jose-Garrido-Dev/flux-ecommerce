<?php

namespace App\Domain\Catalog\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use Auditable;

    public const STATUSES = ['active' => 'Activa', 'archived' => 'Archivada'];

    protected $fillable = [
        'name', 'slug', 'description', 'logo_media_id', 'banner_media_id', 'website', 'country', 'status',
        'show_on_home', 'position', 'seo_title', 'seo_description',
    ];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            $brand->normalized_name = static::normalize($brand->name);
            $brand->slug ??= Str::slug($brand->name);
        });
    }

    protected function casts(): array
    {
        return ['show_on_home' => 'boolean', 'position' => 'integer'];
    }

    /** "Cordillera  Studio" y "cordillera studio" son la misma marca. */
    public static function normalize(string $name): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim($name))));
    }

    public function banner(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'banner_media_id');
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }
}
