<?php

namespace App\Policies;

class PromotionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'promotions';
    }
}
