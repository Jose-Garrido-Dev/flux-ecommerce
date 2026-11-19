<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock de una variante en una bodega. No se modifica directamente:
 * todo cambio pasa por InventoryService, que deja un movimiento.
 */
class InventoryLevel extends Model
{
    protected $fillable = ['variant_id', 'warehouse_id', 'on_hand', 'reserved'];

    protected $attributes = ['on_hand' => 0, 'reserved' => 0];

    protected function casts(): array
    {
        return ['on_hand' => 'integer', 'reserved' => 'integer'];
    }

    public function available(): int
    {
        return $this->on_hand - $this->reserved;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
