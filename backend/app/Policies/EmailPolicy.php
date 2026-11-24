<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Plantillas e historial de correos: los administra quien tiene settings.manage. */
class EmailPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'settings';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('settings.manage');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can('settings.manage');
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
