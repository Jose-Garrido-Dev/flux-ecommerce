<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\GatewayRegistry;
use App\Domain\Payments\Services\PaymentProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Regreso del cliente desde la pasarela (Webpay vuelve por GET o POST con token_ws, TBK_TOKEN o TBK_ORDEN_COMPRA).
 * Confirma el pago desde el servidor y lleva al cliente a la página de su pedido en la tienda.
 * Recargar esta URL o volver dos veces no paga ni descuenta stock dos veces.
 */
class PaymentReturnController extends Controller
{
    public function __invoke(Request $request, string $gateway, GatewayRegistry $registry, PaymentProcessor $processor): RedirectResponse
    {
        $store = rtrim((string) config('flux.storefront_url'), '/');
        if (! $registry->has($gateway)) {
            return redirect()->away($store.'/pago/resultado?estado=desconocido', 303);
        }

        try {
            [$order, $outcome] = $processor->handleReturn($gateway, $request->all());
        } catch (GatewayException) {
            [$order, $outcome] = [null, 'pendiente'];
        }

        if (! $order) {
            return redirect()->away($store.'/pago/resultado?estado='.($outcome === 'unknown' ? 'desconocido' : 'pendiente'), 303);
        }

        $estado = match ($outcome) {
            'paid' => 'aprobado', 'rejected' => 'rechazado', 'aborted' => 'anulado', default => 'pendiente',
        };

        return redirect()->away($store.'/pedido/'.OrderTokens::for($order->uuid).'?pago='.$estado, 303);
    }
}
