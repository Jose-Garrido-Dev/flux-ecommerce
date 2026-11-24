<?php

namespace App\Domain\Shipping\Mail;

use App\Domain\Orders\Mail\OrderMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\Shipping\Services\ShipmentTokens;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Al cliente: su pedido salió, está listo para retiro, o fue entregado. */
class ShipmentMail extends OrderMail
{
    public const SUBJECTS = [
        'shipped' => 'Tu pedido N.º :number va en camino',
        'ready_for_pickup' => 'Tu pedido N.º :number está listo para retiro',
        'delivered' => 'Entregamos tu pedido N.º :number',
        'picked_up' => 'Retiraste tu pedido N.º :number',
    ];

    public function __construct(Order $order, public Shipment $shipment, public string $kind)
    {
        parent::__construct($order);
    }

    public function envelope(): Envelope
    {
        $subject = str_replace(':number', (string) $this->order->number, self::SUBJECTS[$this->kind] ?? self::SUBJECTS['shipped']);

        return new Envelope(subject: "{$subject} · {$this->storeName()}");
    }

    public function content(): Content
    {
        $this->shipment->loadMissing('items.orderItem');

        return new Content(markdown: 'mail.shipping.status', with: [
            ...$this->common(),
            'shipment' => $this->shipment,
            'kind' => $this->kind,
            'courierUrl' => app(ShipmentService::class)->trackingUrl($this->shipment),
            'followUrl' => rtrim((string) config('flux.storefront_url'), '/').'/seguimiento/'.ShipmentTokens::for($this->shipment->uuid),
        ]);
    }
}
