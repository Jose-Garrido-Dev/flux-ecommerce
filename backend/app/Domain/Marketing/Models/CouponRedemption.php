<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['coupon_id', 'order_id', 'customer_id', 'email', 'discount_amount'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
