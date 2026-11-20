<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Pedidos: se crean solo desde la tienda y nunca se borran. Los cambios van por acciones con permiso propio. */
class OrderPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'orders';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
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
