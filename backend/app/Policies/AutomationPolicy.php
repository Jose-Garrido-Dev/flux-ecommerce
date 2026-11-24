<?php

namespace App\Policies;

class AutomationPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'marketing';
    }
}
