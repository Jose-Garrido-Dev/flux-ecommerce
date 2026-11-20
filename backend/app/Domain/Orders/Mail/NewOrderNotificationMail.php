<?php

namespace App\Domain\Orders\Mail;

use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A la tienda: llegó un pedido nuevo. */
class NewOrderNotificationMail extends OrderMail
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nuevo pedido N.º {$this->order->number} por ".'$'.number_format($this->order->grand_total, 0, ',', '.'));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.new-order', with: [
            ...$this->common(),
            'adminUrl' => url('/admin/orders/'.$this->order->getRouteKey()),
        ]);
    }
}
