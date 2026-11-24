<?php

namespace App\Policies;

class CouponPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'coupons';
    }
}
