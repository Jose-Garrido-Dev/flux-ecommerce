<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Política base: traduce las habilidades de Laravel/Filament a permisos "modulo.accion".
 * Cada recurso define su módulo. El superadministrador pasa por Gate::before.
 */
abstract class ModulePolicy
{
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->module().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->module().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->module().'.create');
    }

    public function update(User $user, Model $record): bool
    {
        return $user->can($this->module().'.update');
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->module().'.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can($this->module().'.delete');
    }
}
