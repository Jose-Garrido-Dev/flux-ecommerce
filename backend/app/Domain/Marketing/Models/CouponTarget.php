<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

class CouponTarget extends Model
{
    public $timestamps = false;

    protected $fillable = ['coupon_id', 'target_type', 'target_id', 'mode'];
}
