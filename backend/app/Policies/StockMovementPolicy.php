<?php

namespace App\Policies;

use App\Models\User;

/** Los movimientos se registran desde Existencias y nunca se editan: se corrigen con contramovimientos. */
class StockMovementPolicy extends InventoryPolicy
{
    public function create(User $user): bool
    {
        return false;
    }
}
