<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'users';
    }

    /** Nadie se elimina a sí mismo; los usuarios se desactivan en lugar de borrarse. */
    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
