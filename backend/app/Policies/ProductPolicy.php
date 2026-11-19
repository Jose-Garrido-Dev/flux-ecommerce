<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'products';
    }

    public function archive(User $user, Model $record): bool
    {
        return $user->can('products.archive');
    }

    public function replicate(User $user, Model $record): bool
    {
        return $user->can('products.create');
    }

    public function import(User $user): bool
    {
        return $user->can('products.import');
    }

    public function export(User $user): bool
    {
        return $user->can('products.export');
    }
}
