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

    /** Aplica un cupón. Si no corresponde, responde 422 con el motivo y el carrito queda como estaba. */
    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']], ['code.required' => 'Escribe el código del cupón.']);
        $cart = $this->cart($request, $this->carts);
        if (! $cart) {
            return $this->problem(404, 'cart_not_found', 'El carrito no existe o ya se convirtió en un pedido.');
        }
        $customer = $this->customer($request);

        return $this->respond($this->carts->applyCoupon($cart, $data['code'], $customer?->email, $customer?->id));
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        $cart = $this->cart($request, $this->carts);
        if (! $cart) {
            return $this->problem(404, 'cart_not_found', 'El carrito no existe o ya se convirtió en un pedido.');
        }

        return $this->respond($this->carts->removeCoupon($cart));
    }

    private function respond(?Cart $cart, ?string $token = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => [...self::publicSummary($cart ? $this->carts->summary($cart) : null), 'currency' => 'CLP'], 'token' => $token], $status);
    }

    /** Lo que ve la tienda del resumen: sin costos ni ids internos. */
    public static function publicSummary(?array $summary): array
    {
        $summary ??= [
            'items' => [], 'count' => 0, 'subtotal' => 0, 'discount_total' => 0, 'total' => 0, 'tax_total' => 0, 'warnings' => [],
            'discounts' => ['coupon' => null, 'coupon_error' => null, 'promotions' => [], 'free_shipping' => false],
        ];
        $summary['items'] = array_map(fn ($l) => array_diff_key($l, array_flip(['unit_cost', 'variant_id', 'tax_rate_bp', 'product_id', 'brand_id', 'category_ids'])), $summary['items']);
        if ($summary['discounts']['coupon'] ?? null) {
            unset($summary['discounts']['coupon']['id']);
        }
        $summary['discounts']['promotions'] = array_map(fn ($p) => ['name' => $p['name'], 'amount' => $p['amount']], $summary['discounts']['promotions'] ?? []);

        return $summary;
    }
}
