<?php

namespace App\Domain\Orders\Console;

use App\Domain\Orders\Services\OrderPayments;
use Illuminate\Console\Command;

/** Cancela los pedidos cuyo plazo de pago venció y libera su stock. Lo ejecuta el scheduler cada minuto. */
class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancela los pedidos con el plazo de pago vencido y libera su stock';

    public function handle(OrderPayments $payments): int
    {
        $total = 0;
        do {
            $done = $payments->expireOverdue();
            $total += $done;
        } while ($done === 200);

        $this->info("Pedidos vencidos: $total");

        return self::SUCCESS;
    }
}
