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

    protected $fillable = ['name', 'slug', 'description', 'logo_media_id', 'website', 'status', 'show_on_home', 'position'];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            $brand->normalized_name = Str::lower(Str::ascii(trim($brand->name)));
            $brand->slug ??= Str::slug($brand->name);
        });
    }

    protected function casts(): array
    {
        return ['show_on_home' => 'boolean', 'position' => 'integer'];
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
