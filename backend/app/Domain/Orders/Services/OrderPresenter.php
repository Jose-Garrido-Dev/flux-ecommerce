<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Payment;
use App\Domain\Payments\Gateways\WebpayPlus\WebpayPlusGateway;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\Shipping\Services\ShipmentTokens;

/** Forma pública del pedido para la tienda. Nunca incluye costos ni datos internos. */
class OrderPresenter
{
    public function __construct(private readonly PaymentSettings $payments, private readonly ShipmentService $shipments) {}

    /** @param bool $full con sesión del dueño: dirección completa. Por token: solo comuna y región. */
    public function present(Order $order, bool $full): array
    {
        $order->loadMissing(['items', 'shippingLine', 'events', 'latestPayment', 'shipments.items.orderItem', 'shipments.events']);
        $address = $order->shipping_address;
        $payment = $order->latestPayment;

        return [
            'number' => $order->number,
            'uuid' => $full ? $order->uuid : null,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'status' => $order->status,
            'status_label' => Order::STATUSES[$order->status] ?? $order->status,
            'payment_status' => $order->payment_status,
            'payment_status_label' => Order::PAYMENT_STATUSES[$order->payment_status] ?? $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'fulfillment_status_label' => Order::FULFILLMENT_STATUSES[$order->fulfillment_status] ?? $order->fulfillment_status,
            'first_name' => $order->first_name,
            'email' => $full ? $order->email : null,
            'items' => $order->items->map(fn (OrderItem $i) => [
                'name' => $i->product_name,
                'variant_label' => $i->variant_label,
                'sku' => $i->sku,
                'image' => $i->image_path ? ['path' => $i->image_path, 'alt' => $i->product_name] : null,
                'quantity' => $i->quantity,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total,
            ])->all(),
            'subtotal' => $order->subtotal,
            'shipping_total' => $order->shipping_total,
            'tax_total' => $order->tax_total,
            'grand_total' => $order->grand_total,
            'shipping' => [
                'method' => $order->shippingLine?->method_name,
                'type' => $order->shippingLine?->type,
                'estimated_days' => $order->shippingLine?->estimated_days,
                'pickup_address' => $order->shippingLine?->pickup_address,
                'address' => $address ? ($full ? $address : [
                    'commune' => $address['commune'] ?? null,
                    'region' => $address['region'] ?? null,
                ]) : null,
            ],
            'payment' => [
                'method' => $payment?->methodLabel() ?? '—',
                'method_code' => $payment?->method_code,
                'status' => $payment?->status,
                'status_label' => $payment ? (Payment::STATUSES[$payment->status] ?? $payment->status) : null,
                'expires_at' => $payment && in_array($payment->status, ['pending', 'failed', 'aborted'], true) ? $payment->expires_at?->toIso8601String() : null,
                'transfer' => $payment?->status === 'pending' && $payment->method_code === 'bank_transfer' ? $this->payments->transferInstructions() : null,
                // Pago con pasarela que se puede iniciar o reintentar mientras el pedido siga pendiente
                'can_pay' => $order->status === 'pending' && $payment && $payment->method_code !== 'bank_transfer'
                    && in_array($payment->status, ['pending', 'failed', 'aborted'], true)
                    && $this->payments->isAvailable($payment->method_code),
                'failure_reason' => in_array($payment?->status, ['failed', 'aborted'], true) ? $payment->failure_reason : null,
                'card_last4' => $payment?->status === 'paid' ? $payment->card_last4 : null,
                'payment_type' => $payment?->status === 'paid' && $payment->payment_type ? (WebpayPlusGateway::PAYMENT_TYPES[$payment->payment_type] ?? null) : null,
                'installments' => $payment?->status === 'paid' && ($payment->installments ?? 0) > 1 ? $payment->installments : null,
                'authorization_code' => $payment?->status === 'paid' ? $payment->authorization_code : null,
                'refunded_amount' => $order->refunded_total,
            ],
            'shipments' => $order->shipments->where('status', '!=', 'cancelled')->map(fn (Shipment $s) => $this->shipment($s))->values()->all(),
            'document_type' => $order->document_type,
            'events' => $order->events->where('visible_to_customer', true)->map(fn (OrderEvent $e) => [
                'type' => $e->type,
                'label' => $e->label(),
                'at' => $e->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** Despacho visible para el cliente: estado, courier, seguimiento y productos. Nunca nombres ni direcciones. */
    public function shipment(Shipment $s): array
    {
        return [
            'status' => $s->status,
            'status_label' => $s->statusLabel(),
            'carrier' => $s->carrier_name,
            'tracking_number' => $s->tracking_number,
            'tracking_url' => $this->shipments->trackingUrl($s),
            'follow_token' => ShipmentTokens::for($s->uuid),
            'shipped_at' => $s->shipped_at?->toIso8601String(),
            'delivered_at' => $s->delivered_at?->toIso8601String(),
            'items' => $s->items->map(fn ($i) => [
                'name' => $i->orderItem?->product_name,
                'variant_label' => $i->orderItem?->variant_label,
                'quantity' => $i->quantity,
            ])->values()->all(),
            'events' => $s->events->map(fn ($e) => [
                'status' => $e->status,
                'label' => Shipment::STATUSES[$e->status] ?? $e->status,
                'description' => $e->description,
                'location' => $e->location,
                'at' => $e->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
