<?php

namespace App\Policies;

class BlogPostPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'content';
    }
}
