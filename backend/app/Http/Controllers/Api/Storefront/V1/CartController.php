<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Carrito. El navegador nunca envía precios: solo SKU y cantidad. */
class CartController extends StorefrontController
{
    public function __construct(private readonly CartService $carts) {}

    public function show(Request $request): JsonResponse
    {
        $cart = $this->cart($request, $this->carts);

        return $this->respond($cart);
    }

    /** Agrega unidades. Si no hay carrito, lo crea y devuelve su token una sola vez. */
    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);
        $token = null;
        $cart = $this->cart($request, $this->carts);
        if (! $cart) {
            [$cart, $token] = $this->carts->create($this->customer($request));
        }
        $cart = $this->carts->setQuantity($cart, $data['sku'], (int) $data['quantity'], add: true);

        return $this->respond($cart, $token, 201);
    }

    public function update(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY]]);
        $cart = $this->cart($request, $this->carts);
        if (! $cart) {
            return $this->problem(404, 'cart_not_found', 'El carrito no existe o ya se convirtió en un pedido.');
        }

        return $this->respond($this->carts->setQuantity($cart, $sku, (int) $data['quantity']));
    }

    public function remove(Request $request, string $sku): JsonResponse
    {
        $cart = $this->cart($request, $this->carts);
        if (! $cart) {
            return $this->problem(404, 'cart_not_found', 'El carrito no existe o ya se convirtió en un pedido.');
        }

        return $this->respond($this->carts->remove($cart, $sku));
    }

    private function respond(?Cart $cart, ?string $token = null, int $status = 200): JsonResponse
    {
        $summary = $cart ? $this->carts->summary($cart) : ['items' => [], 'count' => 0, 'subtotal' => 0, 'tax_total' => 0, 'warnings' => []];
        $summary['items'] = array_map(fn ($l) => array_diff_key($l, array_flip(['unit_cost', 'variant_id', 'tax_rate_bp'])), $summary['items']);

        return response()->json(['data' => [...$summary, 'currency' => 'CLP'], 'token' => $token], $status);
    }
}
