<?php

namespace App\Domain\Orders\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al cliente: recibimos el pago. */
class PaymentConfirmedMail extends OrderMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Pago confirmado: pedido N.º {$this->order->number} · {$this->storeName()}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.paid', with: $this->common());
    }
}
