<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ModuleStatusPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'system';
    }

    public function create(User $user): bool
    {
        return false; // los 34 módulos vienen del seeder
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
