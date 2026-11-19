<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Configuración de inventario de una variante: si controla stock, sobreventa y stock mínimo. */
class InventoryItem extends Model
{
    use Auditable;

    protected $fillable = ['variant_id', 'track_stock', 'allow_backorder', 'min_stock'];

    protected $attributes = ['track_stock' => true, 'allow_backorder' => false, 'min_stock' => 0];

    protected function casts(): array
    {
        return ['track_stock' => 'boolean', 'allow_backorder' => 'boolean', 'min_stock' => 'integer'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
