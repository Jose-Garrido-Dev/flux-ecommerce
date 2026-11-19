<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Atributo descriptivo del producto (no variante): material, origen, garantía... */
class ProductAttributeValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'attribute_id', 'attribute_value_id', 'value_text', 'value_number'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }

    public function displayValue(): string
    {
        if ($this->value) {
            return $this->value->label;
        }
        if ($this->value_number !== null) {
            $n = rtrim(rtrim(number_format((float) $this->value_number, 4, ',', '.'), '0'), ',');

            return trim($n.' '.($this->attribute->unit ?? ''));
        }

        return (string) $this->value_text;
    }
}
