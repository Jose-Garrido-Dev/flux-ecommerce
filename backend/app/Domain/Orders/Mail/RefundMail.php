<?php

namespace App\Domain\Orders\Mail;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Refund;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al cliente: hicimos un reembolso. */
class RefundMail extends OrderMail
{
    public function __construct(Order $order, public Refund $refund)
    {
        parent::__construct($order);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reembolso del pedido N.º {$this->order->number} · {$this->storeName()}");
    }

    public function content(): Content
    {
        $method = $this->refund->payment?->method_code;

        return new Content(markdown: 'mail.orders.refunded', with: [
            ...$this->common(),
            'refund' => $this->refund,
            'byCard' => $method !== null && $method !== 'bank_transfer',
        ]);
    }
}
