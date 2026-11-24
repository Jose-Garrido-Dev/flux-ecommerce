<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Orders\Services\OrderPresenter;
use App\Domain\Shipping\Services\ShipmentTokens;
use Illuminate\Http\JsonResponse;

/** Seguimiento público de un despacho con su enlace: sin nombres, correos ni direcciones (solo comuna y región). */
class ShipmentTrackingController extends StorefrontController
{
    public function show(string $token, OrderPresenter $presenter): JsonResponse
    {
        $shipment = ShipmentTokens::find($token);
        if (! $shipment || $shipment->status === 'cancelled') {
            return $this->problem(404, 'not_found', 'El seguimiento no existe o el enlace no es válido.');
        }
        $shipment->load(['items.orderItem', 'events', 'order.shippingLine']);
        $address = $shipment->order->shipping_address;

        return response()->json(['data' => [
            ...array_diff_key($presenter->shipment($shipment), ['follow_token' => 1]),
            'order_number' => $shipment->order->number,
            'delivery' => $shipment->order->shippingLine?->type === 'pickup' ? 'pickup' : 'delivery',
            'destination' => $address ? ['commune' => $address['commune'] ?? null, 'region' => $address['region'] ?? null] : null,
            'estimated_days' => $shipment->order->shippingLine?->estimated_days,
            'pickup_address' => $shipment->order->shippingLine?->pickup_address,
        ]]);
    }
}
