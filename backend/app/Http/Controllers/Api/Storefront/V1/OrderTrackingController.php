<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Orders\Services\OrderPresenter;
use App\Domain\Orders\Services\OrderTokens;
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
}
