<?php

namespace App\Policies;

class CampaignPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'marketing';
    }
}
