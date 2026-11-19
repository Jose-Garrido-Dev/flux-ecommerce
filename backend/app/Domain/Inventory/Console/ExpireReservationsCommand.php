<?php

namespace App\Domain\Inventory\Console;

use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Console\Command;

/** Libera las reservas de stock vencidas. Lo ejecuta el scheduler cada minuto. */
class ExpireReservationsCommand extends Command
{
    protected $signature = 'inventory:expire-reservations';

    protected $description = 'Libera las reservas de stock vencidas';

    public function handle(InventoryService $inventory): int
    {
        $total = 0;
        do {
            $released = $inventory->expireDue();
            $total += $released;
        } while ($released === 500);

        $this->info("Reservas vencidas liberadas: $total");

        return self::SUCCESS;
    }
}
