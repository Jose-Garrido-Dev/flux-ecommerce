<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** La auditoría solo se consulta: nunca se crea, edita ni borra desde el panel. */
class AuditLogPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'audit';
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
