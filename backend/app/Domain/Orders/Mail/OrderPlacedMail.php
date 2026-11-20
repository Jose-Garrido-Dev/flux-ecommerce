<?php

namespace App\Domain\Orders\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al cliente: pedido recibido, con los datos para transferir. */
class OrderPlacedMail extends OrderMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Recibimos tu pedido N.º {$this->order->number} · {$this->storeName()}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.placed', with: $this->common());
    }
}
