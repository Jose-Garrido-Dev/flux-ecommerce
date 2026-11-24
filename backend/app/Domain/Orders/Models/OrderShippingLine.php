<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShippingLine extends Model
{
    protected $fillable = ['order_id', 'method_id', 'method_code', 'method_name', 'type', 'amount', 'tax_amount', 'estimated_days',
        'carrier_code', 'zone_id', 'zone_name', 'chargeable_weight_g', 'pickup_address'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'tax_amount' => 'integer', 'chargeable_weight_g' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
