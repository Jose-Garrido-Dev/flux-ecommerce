<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CatalogImportPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'products';
    }

    /** Ver y crear importaciones requiere el permiso de importar productos. */
    public function viewAny(User $user): bool
    {
        return $user->can('products.import');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can('products.import');
    }

    public function create(User $user): bool
    {
        return $user->can('products.import');
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
