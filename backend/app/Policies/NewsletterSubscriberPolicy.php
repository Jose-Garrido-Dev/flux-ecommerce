<?php

namespace App\Policies;

class NewsletterSubscriberPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'marketing';
    }
}
