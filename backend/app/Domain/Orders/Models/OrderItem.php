<?php

namespace App\Domain\Orders\Models;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'variant_id', 'product_name', 'variant_label', 'sku', 'image_path', 'quantity', 'unit_price',
        'compare_at_price', 'unit_cost', 'discount_amount', 'tax_rate_bp', 'tax_amount', 'line_total',
    ];

    protected function casts(): array
    {
        return array_fill_keys(['quantity', 'unit_price', 'compare_at_price', 'unit_cost', 'discount_amount', 'tax_rate_bp', 'tax_amount', 'line_total'], 'integer');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
