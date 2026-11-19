<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Existencias, reservas y transferencias: el stock solo cambia mediante movimientos,
 * así que estas filas no se editan ni se borran desde el panel.
 */
class InventoryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'inventory';
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
