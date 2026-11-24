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

    /** Lo que el cliente pagó por estas unidades, descontando la parte del cupón o promoción de la línea. */
    public function paidFor(int $quantity): int
    {
        if ($this->quantity <= 0) {
            return 0;
        }
        $quantity = min($quantity, $this->quantity);
        if ($quantity === $this->quantity) {
            return $this->line_total - $this->discount_amount;
        }

        return intdiv(($this->line_total - $this->discount_amount) * $quantity, $this->quantity);
    }
}
