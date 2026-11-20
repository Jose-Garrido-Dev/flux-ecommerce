<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Customers\Models\Customer;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/** Utilidades comunes de la API de la tienda: cliente autenticado, carrito, IP real y errores. */
abstract class StorefrontController extends Controller
{
    /** Cliente autenticado por token (opcional en rutas públicas). */
    protected function customer(Request $request): ?Customer
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        $token = PersonalAccessToken::findToken($bearer);
        $customer = $token?->tokenable;
        if (! $customer instanceof Customer || $customer->status !== 'active'
            || ($token->expires_at && $token->expires_at->isPast())) {
            return null;
        }

        return $customer;
    }

    /** Carrito del cliente o del token X-Cart-Token. Al tener ambos, se unen. */
    protected function cart(Request $request, CartService $carts): ?Cart
    {
        $customer = $this->customer($request);
        $guest = $carts->findByToken($request->header('X-Cart-Token'));
        if ($customer) {
            if ($guest && $guest->customer_id !== $customer->id) {
                return $guest->customer_id === null ? $carts->attachToCustomer($guest, $customer) : $carts->forCustomer($customer);
            }

            return $guest ?? $carts->forCustomer($customer);
        }

        return $guest && $guest->customer_id === null ? $guest : null;
    }

    /**
     * IP del comprador. Next.js consulta desde su servidor y la informa en X-Flux-Client-IP;
     * solo se le cree si trae el secreto compartido.
     */
    public static function clientIp(Request $request): string
    {
        $secret = (string) config('flux.revalidate_secret');
        $sent = (string) $request->header('X-Flux-Storefront-Key');
        $forwarded = (string) $request->header('X-Flux-Client-IP');
        if ($secret !== '' && $sent !== '' && hash_equals($secret, $sent) && filter_var($forwarded, FILTER_VALIDATE_IP)) {
            return $forwarded;
        }

        return (string) $request->ip();
    }

    protected function problem(int $status, string $code, string $detail, array $extra = []): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => match ($status) {
                401 => 'No autenticado', 404 => 'No encontrado', 409 => 'Conflicto', 429 => 'Demasiados intentos', default => 'Solicitud no válida',
            },
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
            ...$extra,
        ], $status, ['Content-Type' => 'application/problem+json']);
    }
}
