<?php

namespace App\Policies;

class ReturnPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'returns';
    }
}
