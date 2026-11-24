<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Cart\Services\CartService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\CheckoutException;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Orders\Services\PaymentSettings;
use App\Domain\Orders\Services\PlaceOrder;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Services\RateEngine;
use App\Domain\Shipping\Services\ShippingPackage;
use App\Support\Rut;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Checkout: opciones de envío y pago, y creación del pedido (diseño técnico, sección 8.1). */
class CheckoutController extends StorefrontController
{
    public function __construct(
        private readonly CartService $carts,
        private readonly RateEngine $shipping,
        private readonly PaymentSettings $payments,
    ) {}

    /** Métodos de envío para la región elegida y medios de pago disponibles, con el total actualizado. */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate(['region_code' => ['nullable', 'string', 'max:8'], 'commune' => ['nullable', 'string', 'max:80']]);
        $cart = $this->cart($request, $this->carts);
        $summary = $cart ? $this->carts->summary($cart) : null;
        $regionId = isset($data['region_code']) ? Region::query()->where('code', $data['region_code'])->value('id') : null;
        $subtotal = $summary['subtotal'] ?? 0;

        return response()->json(['data' => [
            'shipping_methods' => $this->shipping->quote($regionId, $data['commune'] ?? null, $subtotal, ShippingPackage::fromLines(array_filter($summary['items'] ?? [], fn ($l) => $l['purchasable'])))
                ->map(fn ($m) => array_diff_key($m, array_flip(['method_id', 'tax_amount', 'zone_id', 'chargeable_weight_g'])))->all(),
            'payment_methods' => $this->payments->methods(),
            'subtotal' => $subtotal,
        ]]);
    }

    public function store(Request $request, PlaceOrder $placeOrder): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');
        if ($key === '' || strlen($key) > 100) {
            return $this->problem(400, 'idempotency_key_required', 'Falta la cabecera Idempotency-Key.');
        }
        $customer = $this->customer($request);
        $pickup = fn () => ShippingMethod::query()->where('code', $request->input('shipping_method'))->value('type') === 'pickup';

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'shipping_method' => ['required', 'string', 'max:40'],
            'payment_method' => ['required', 'string', 'max:40'],
            'address' => [Rule::requiredIf(fn () => ! $pickup()), 'array'],
            'address.recipient' => [Rule::requiredIf(fn () => ! $pickup()), 'string', 'max:120'],
            'address.phone' => [Rule::requiredIf(fn () => ! $pickup()), 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'address.street' => [Rule::requiredIf(fn () => ! $pickup()), 'string', 'max:150'],
            'address.number' => [Rule::requiredIf(fn () => ! $pickup()), 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:40'],
            'address.region_code' => [Rule::requiredIf(fn () => ! $pickup()), 'string', Rule::exists('regions', 'code')->where('is_enabled', true)],
            'address.commune' => [Rule::requiredIf(fn () => ! $pickup()), 'string', 'max:80', $this->communeRule()],
            'address.reference' => ['nullable', 'string', 'max:200'],
            'document_type' => ['nullable', Rule::in(array_keys(Order::DOCUMENT_TYPES))],
            'billing_rut' => ['required_if:document_type,factura', 'nullable', 'string', 'max:12', function (string $attr, mixed $value, Closure $fail) {
                if (filled($value) && ! Rut::isValid((string) $value)) {
                    $fail('El RUT de la empresa no es válido.');
                }
            }],
            'billing_name' => ['required_if:document_type,factura', 'nullable', 'string', 'max:150'],
            'billing_activity' => ['required_if:document_type,factura', 'nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:500'],
            'accepts_terms' => ['accepted'],
            'expected_total' => ['nullable', 'integer', 'min:0'],
        ], [
            'accepts_terms.accepted' => 'Debes aceptar los términos y condiciones.',
            'billing_rut.required_if' => 'Para factura indica el RUT de la empresa.',
            'billing_name.required_if' => 'Para factura indica la razón social.',
            'billing_activity.required_if' => 'Para factura indica el giro.',
        ]);
        if (filled($data['billing_rut'] ?? null)) {
            $data['billing_rut'] = Rut::format($data['billing_rut']);
        }

        $cart = $this->cart($request, $this->carts);
        $existing = Order::query()->where('idempotency_key', $key)->first();
        if (! $cart && ! $existing) {
            return $this->problem(404, 'cart_not_found', 'El carrito no existe o ya se convirtió en un pedido.');
        }

        try {
            $order = $existing ?? $placeOrder->handle($cart, $data, $customer, $key, self::clientIp($request));
        } catch (CheckoutException $e) {
            return $this->problem($e->status, $e->errorCode, $e->getMessage(), $e->summary ? ['cart' => $e->summary] : []);
        }

        return response()->json(['data' => [
            'number' => $order->number,
            'token' => OrderTokens::for($order->uuid),
            'grand_total' => $order->grand_total,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'payment_method' => $method = $order->latestPayment?->method_code,
            // pay: hay que llevar al cliente a la pasarela (POST /orders/{token}/pay); instructions: mostrar los datos de transferencia
            'next' => $method === 'bank_transfer' || $order->payment_status === 'paid' ? 'instructions' : 'pay',
        ]], $order->wasRecentlyCreated ? 201 : 200);
    }

    /** Si ya están cargadas las comunas oficiales, la comuna debe ser una de la región elegida. */
    private function communeRule(): Closure
    {
        return function (string $attr, mixed $value, Closure $fail) {
            $region = Region::query()->where('code', request()->input('address.region_code'))->first();
            if (! $region || ! $region->communes()->exists()) {
                return;
            }
            if (! $region->communes()->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])->exists()) {
                $fail('La comuna no pertenece a la región elegida.');
            }
        };
    }
}
