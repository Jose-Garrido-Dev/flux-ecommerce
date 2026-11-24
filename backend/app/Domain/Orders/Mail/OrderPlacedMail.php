<?php

namespace App\Domain\Orders\Mail;

/** Al cliente: pedido recibido, con los datos para transferir. */
class OrderPlacedMail extends OrderMail
{
    public function templateKey(): string
    {
        return 'order_placed';
    }

    protected function markdownView(): string
    {
        return 'mail.orders.placed';
    }
}
