<?php

namespace App\Domain\Orders\Console;

use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Payments\Services\PaymentProcessor;
use Illuminate\Console\Command;

/**
 * Lo ejecuta el scheduler cada minuto:
 * 1. Concilia con la pasarela los pagos que quedaron abiertos (el cliente pagó pero no volvió a la tienda).
 * 2. Cancela los pedidos cuyo plazo de pago venció y libera su stock.
 */
class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'orders:expire-unpaid';

    protected $description = 'Cancela los pedidos con el plazo de pago vencido y libera su stock';

    public function handle(OrderPayments $payments, PaymentProcessor $processor): int
    {
        $reconciled = $processor->reconcile();
        if ($reconciled !== []) {
            $this->info('Pagos con pasarela revisados: '.json_encode($reconciled));
        }

        $total = 0;
        do {
            $done = $payments->expireOverdue();
            $total += $done;
        } while ($done === 200);

        $this->info("Pedidos vencidos: $total");

        return self::SUCCESS;
    }
}
