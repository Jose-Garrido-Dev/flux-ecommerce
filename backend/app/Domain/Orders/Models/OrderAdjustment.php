<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cada descuento aplicado a un pedido (cupón o promoción), para saber de dónde salió. */
class OrderAdjustment extends Model
{
    public const UPDATED_AT = null;

    public const SOURCES = ['coupon' => 'Cupón', 'promotion' => 'Promoción', 'manual' => 'Manual'];

    protected $fillable = ['order_id', 'source', 'source_id', 'label', 'target', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
