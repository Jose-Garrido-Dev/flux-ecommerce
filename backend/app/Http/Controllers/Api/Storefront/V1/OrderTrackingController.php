<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\CheckoutException;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\OrderPresenter;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\PaymentProcessor;
use App\Domain\Returns\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /** El cliente cancela un pedido que aún no paga. Responde 422 si el pedido ya no se puede cancelar. */
    public function cancel(string $token, OrderPayments $payments, OrderPresenter $presenter): JsonResponse
    {
        $order = OrderTokens::find($token);
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe o el enlace no es válido.');
        }
        $payments->cancel($order, 'Lo cancelaste desde la tienda.', 'customer');

        return response()->json(['data' => $presenter->present($order->fresh(), full: false)]);
    }

    /** Solicitud de devolución o cambio con el enlace del pedido. */
    public function requestReturn(string $token, Request $request, ReturnService $returns, OrderPresenter $presenter): JsonResponse
    {
        $order = OrderTokens::find($token);
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe o el enlace no es válido.');
        }

        return self::storeReturn($order, $request, $returns, $presenter);
    }

    /** El cliente anula una solicitud de devolución mientras no envíe el producto. */
    public function cancelReturn(string $token, string $uuid, ReturnService $returns, OrderPresenter $presenter): JsonResponse
    {
        $order = OrderTokens::find($token);
        $return = $order ? $order->returns()->where('uuid', $uuid)->first() : null;
        if (! $return) {
            return $this->problem(404, 'not_found', 'La solicitud no existe.');
        }
        $returns->cancel($return);

        return response()->json(['data' => $presenter->present($order->fresh(), full: false)]);
    }

    public static function storeReturn(Order $order, Request $request, ReturnService $returns, OrderPresenter $presenter, bool $full = false): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'in:return,exchange'],
            'reason' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);
        $items = collect($data['items'])->mapWithKeys(fn ($i) => [(int) $i['id'] => (int) $i['quantity']])->all();
        $return = $returns->request($order, $items, $data['type'], $data['reason'], $data['notes'] ?? null, 'customer');

        return response()->json(['data' => [
            'return' => $presenter->returnRequest($return->fresh(['items.orderItem'])),
            'order' => $presenter->present($order->fresh(), full: $full),
        ]], 201);
    }
}
