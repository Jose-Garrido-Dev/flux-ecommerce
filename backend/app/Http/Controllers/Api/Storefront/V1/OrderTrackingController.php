<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Orders\Services\CheckoutException;
use App\Domain\Orders\Services\OrderPresenter;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\PaymentProcessor;
use Illuminate\Http\JsonResponse;

/** Seguimiento y resultado de compra por token (sin sesión). Muestra el estado real del pago. */
class OrderTrackingController extends StorefrontController
{
    public function show(string $token, OrderPresenter $presenter): JsonResponse
    {
        $order = OrderTokens::find($token);
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe o el enlace no es válido.');
        }

        return response()->json(['data' => $presenter->present($order, full: false)]);
    }

    /**
     * Inicia (o reintenta) el pago con pasarela. Devuelve el formulario al que hay que enviar al cliente.
     * Si el pedido ya está pagado responde paid: true.
     */
    public function pay(string $token, PaymentProcessor $processor): JsonResponse
    {
        $order = OrderTokens::find($token);
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe o el enlace no es válido.');
        }
        try {
            $initiation = $processor->start($order);
        } catch (CheckoutException $e) {
            return $this->problem($e->status, $e->errorCode, $e->getMessage());
        } catch (GatewayException $e) {
            return $this->problem(502, 'gateway_error', $e->getMessage());
        }

        return response()->json(['data' => $initiation ? ['paid' => false, 'redirect' => $initiation->toArray()] : ['paid' => true, 'redirect' => null]]);
    }
}
