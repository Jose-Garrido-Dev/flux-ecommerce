<?php

namespace App\Domain\Orders\Mail;

/** Al cliente: recibimos el pago. */
class PaymentConfirmedMail extends OrderMail
{
    public function templateKey(): string
    {
        return 'payment_confirmed';
    }

    protected function markdownView(): string
    {
        return 'mail.orders.paid';
    }
}
