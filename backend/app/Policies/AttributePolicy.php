<?php

namespace App\Policies;

class AttributePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'attributes';
    }
}
