<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Las regiones son un catálogo oficial: solo se habilitan o deshabilitan. */
class RegionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'shipping';
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
