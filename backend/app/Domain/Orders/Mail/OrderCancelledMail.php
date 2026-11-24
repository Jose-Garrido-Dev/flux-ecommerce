<?php

namespace App\Domain\Orders\Mail;

use App\Domain\Orders\Models\Order;

/** Al cliente: el pedido sin pagar se canceló (tienda, cliente o plazo vencido). */
class OrderCancelledMail extends OrderMail
{
    public function __construct(Order $order, public string $reason)
    {
        parent::__construct($order);
    }

    public function templateKey(): string
    {
        return 'order_cancelled';
    }

    protected function markdownView(): string
    {
        return 'mail.orders.cancelled';
    }

    protected function variables(): array
    {
        return ['motivo' => $this->reason];
    }
}
