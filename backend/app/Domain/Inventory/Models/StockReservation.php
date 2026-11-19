<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stock apartado para un carrito o pedido hasta que se pague o venza. */
class StockReservation extends Model
{
    use HasUuids;

    public const STATUSES = ['active' => 'Activa', 'consumed' => 'Vendida', 'released' => 'Liberada', 'expired' => 'Vencida'];

    public const REFERENCES = ['cart' => 'Carrito', 'order' => 'Pedido'];

    protected $fillable = ['reference_type', 'reference_id', 'variant_id', 'warehouse_id', 'quantity', 'status', 'expires_at', 'closed_at'];

    protected $attributes = ['status' => 'active'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'expires_at' => 'datetime', 'closed_at' => 'datetime'];
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
