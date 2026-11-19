<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AttributeValue extends Model
{
    protected $fillable = ['attribute_id', 'value', 'label', 'color_hex', 'image_media_id', 'position'];

    protected static function booted(): void
    {
        // El valor normalizado se usa en filtros y URLs: "Azul marino" => "azul-marino"
        static::saving(function (AttributeValue $v) {
            $v->value = filled($v->value) ? Str::slug($v->value) : Str::slug($v->label);
        });
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }
}
