<?php

namespace App\Domain\Returns\Models;

use App\Domain\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $fillable = ['return_id', 'order_item_id', 'quantity', 'condition', 'restock'];

    protected function casts(): array
    {
        return ['restock' => 'boolean'];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
