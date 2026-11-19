<?php

namespace App\Policies;

class BrandPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'brands';
    }
}
