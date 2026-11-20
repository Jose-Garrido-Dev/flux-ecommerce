<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Clientes: se registran desde la tienda. En el panel se consultan y se bloquean. */
class CustomerPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'customers';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
