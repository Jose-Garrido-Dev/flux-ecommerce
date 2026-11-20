<?php

namespace App\Policies;

class ShippingMethodPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'shipping';
    }
}
