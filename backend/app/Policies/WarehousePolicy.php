<?php

namespace App\Policies;

class WarehousePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'warehouses';
    }
}
