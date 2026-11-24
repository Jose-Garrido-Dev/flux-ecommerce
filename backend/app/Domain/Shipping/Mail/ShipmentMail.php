<?php

namespace App\Domain\Shipping\Mail;

use App\Domain\Orders\Mail\OrderMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\Shipping\Services\ShipmentTokens;

/** Al cliente: su pedido salió, está listo para retiro, o fue entregado. */
class ShipmentMail extends OrderMail
{
    public const KINDS = ['shipped', 'ready_for_pickup', 'delivered', 'picked_up'];

    public function __construct(Order $order, public Shipment $shipment, public string $kind)
    {
        parent::__construct($order);
    }

    public function templateKey(): string
    {
        return 'shipment_'.(in_array($this->kind, self::KINDS, true) ? $this->kind : 'shipped');
    }

    protected function markdownView(): string
    {
        return 'mail.shipping.status';
    }

    protected function variables(): array
    {
        return ['courier' => $this->shipment->carrier_name, 'seguimiento' => (string) $this->shipment->tracking_number];
    }

    protected function viewData(): array
    {
        $this->shipment->loadMissing('items.orderItem');

        return [
            'shipment' => $this->shipment,
            'kind' => $this->kind,
            'courierUrl' => app(ShipmentService::class)->trackingUrl($this->shipment),
            'followUrl' => rtrim((string) config('flux.storefront_url'), '/').'/seguimiento/'.ShipmentTokens::for($this->shipment->uuid),
        ];
    }
}
