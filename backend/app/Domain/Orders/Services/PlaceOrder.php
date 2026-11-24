<?php

namespace App\Domain\Orders\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\CouponRedemption;
use App\Domain\Marketing\Services\DiscountEngine;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Services\PaymentProcessor;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Services\RateEngine;
use App\Domain\Shipping\Services\ShippingPackage;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crea el pedido (diseño técnico, sección 8.1). En una transacción: recalcula precios e IVA en el servidor,
 * revalida el envío, reserva el stock de forma atómica y guarda la copia fija del pedido.
 * Nada de lo que envía el navegador sobre precios o totales se usa: solo se compara para avisar cambios.
 */
class PlaceOrder
{
    public function __construct(
        private readonly CartService $carts,
        private readonly InventoryService $inventory,
        private readonly RateEngine $shipping,
        private readonly PaymentSettings $payments,
        private readonly DiscountEngine $discounts,
    ) {}

    /**
     * @param  array  $input  datos validados del checkout (contacto, dirección, envío, pago, documento)
     *
     * @throws CheckoutException
     */
    public function handle(Cart $cart, array $input, ?Customer $customer, string $idempotencyKey, ?string $ip = null): Order
    {
        if ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing; // reintento del mismo envío: mismo pedido
        }

        $email = Customer::normalizeEmail($input['email']);
        $summary = $this->carts->summary($cart, $email, $customer?->id);
        $lines = array_values(array_filter($summary['items'], fn ($l) => $l['purchasable']));
        if ($summary['items'] === []) {
            throw new CheckoutException('cart_empty', 'Tu carrito está vacío.', 422);
        }
        if (count($lines) !== count($summary['items'])) {
            throw new CheckoutException('cart_changed', 'Algunos productos de tu carrito ya no están disponibles. Revisa el carrito.', 409, $summary);
        }

        $discounts = $summary['discounts'];
        if ($discounts['coupon_error'] !== null) {
            throw new CheckoutException('coupon_invalid', $discounts['coupon_error'].' Quítalo del carrito para continuar.', 409, $summary);
        }

        $paymentMethod = (string) ($input['payment_method'] ?? '');
        if (! $this->payments->isAvailable($paymentMethod)) {
            throw new CheckoutException('payment_unavailable', 'El medio de pago elegido no está disponible.', 422);
        }

        $region = Region::query()->where('code', $input['address']['region_code'] ?? '')->where('is_enabled', true)->first();
        $method = ShippingMethod::query()->active()->where('code', $input['shipping_method'] ?? '')->first();
        $needsAddress = $method?->type !== 'pickup';
        if (! $method || ($needsAddress && ! $region)) {
            throw new CheckoutException('shipping_unavailable', 'El método de envío elegido no está disponible para tu dirección.', 422);
        }
        // Mismo cálculo que vio el cliente en el checkout: zona, peso (real o volumétrico) y restricciones de los productos
        $commune = $needsAddress ? ($input['address']['commune'] ?? null) : null;
        // El envío gratis por monto se calcula sobre lo que paga el cliente (con descuentos)
        $result = $this->shipping->evaluate($method, $region?->id, $commune, $summary['total'], ShippingPackage::fromLines($lines));
        if (! $result['available']) {
            throw new CheckoutException('shipping_unavailable', 'El método de envío elegido no está disponible para tu dirección o tus productos.', 422);
        }

        $subtotal = array_sum(array_column($lines, 'line_total'));
        $discountTotal = $summary['discount_total'];
        $ship = $result['option'];
        $shippingDiscount = 0;
        if ($discounts['free_shipping'] && $ship['amount'] > 0) {
            $shippingDiscount = $ship['amount'];
            $ship = [...$ship, 'amount' => 0, 'tax_amount' => 0];
        }
        $itemsTax = array_sum(array_column($lines, 'tax_amount'));
        $grandTotal = $subtotal - $discountTotal + $ship['amount'];

        // El total que vio el cliente solo sirve para avisarle si algo cambió; nunca se usa para cobrar
        if (isset($input['expected_total']) && (int) $input['expected_total'] !== $grandTotal) {
            throw new CheckoutException('price_changed', 'Los precios o el envío cambiaron. Revisa el nuevo total antes de confirmar.', 409, [
                ...$summary, 'shipping' => $ship, 'grand_total' => $grandTotal,
            ]);
        }

        $uuid = (string) Str::uuid();
        $token = OrderTokens::for($uuid);
        // Transferencia: plazo configurable (horas). Pasarela: el cliente paga en el momento.
        $manual = $paymentMethod === 'bank_transfer';
        $minutes = $manual ? $this->payments->transferHours() * 60 : PaymentProcessor::GATEWAY_MINUTES;

        try {
            $order = DB::transaction(function () use ($cart, $input, $customer, $idempotencyKey, $uuid, $token, $lines, $subtotal, $ship, $itemsTax, $grandTotal, $method, $region, $needsAddress, $minutes, $paymentMethod, $ip, $email, $discounts, $discountTotal, $shippingDiscount, $summary) {
                // El cupón se bloquea mientras se crea el pedido: dos compras simultáneas no pueden pasar el límite de usos
                $coupon = null;
                if ($discounts['coupon'] !== null) {
                    $coupon = Coupon::query()->with('targets')->whereKey($discounts['coupon']['id'])->lockForUpdate()->first();
                    if ($error = $this->discounts->couponError($coupon, $lines, $subtotal, $email, $customer?->id)) {
                        throw new CheckoutException('coupon_invalid', $error.' Quítalo del carrito para continuar.', 409, $summary);
                    }
                }

                $order = new Order([
                    'idempotency_key' => $idempotencyKey,
                    'customer_id' => $customer?->id,
                    'cart_id' => $cart->id,
                    'email' => $email,
                    'phone' => $input['phone'],
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'subtotal' => $subtotal,
                    'discount_total' => $discountTotal,
                    'tax_total' => $itemsTax + $ship['tax_amount'],
                    'shipping_total' => $ship['amount'],
                    'grand_total' => $grandTotal,
                    'shipping_address' => $needsAddress ? $this->addressSnapshot($input['address'], $region) : null,
                    'document_type' => $input['document_type'] ?? 'boleta',
                    'billing_rut' => $input['billing_rut'] ?? null,
                    'billing_name' => $input['billing_name'] ?? null,
                    'billing_activity' => $input['billing_activity'] ?? null,
                    'notes' => $input['notes'] ?? null,
                    'tracking_token_hash' => OrderTokens::hash($token),
                    'placed_at' => now(),
                ]);
                $order->uuid = $uuid;
                $order->save();

                foreach ($lines as $line) {
                    $order->items()->create([
                        'variant_id' => $line['variant_id'],
                        'product_name' => $line['name'],
                        'variant_label' => $line['variant_label'],
                        'sku' => $line['sku'],
                        'image_path' => $line['image']['path'] ?? null,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'compare_at_price' => $line['compare_at'],
                        'unit_cost' => $line['unit_cost'],
                        'discount_amount' => $line['discount'],
                        'tax_rate_bp' => $line['tax_rate_bp'],
                        'tax_amount' => $line['tax_amount'],
                        'line_total' => $line['line_total'],
                    ]);
                    // Reserva atómica: si otra compra se llevó la última unidad, falla y se deshace todo el pedido
                    $this->inventory->reserve($line['variant_id'], $line['quantity'], 'order', $uuid, $minutes);
                }

                $order->shippingLine()->create([
                    'method_id' => $method->id, 'method_code' => $method->code, 'method_name' => $method->name, 'type' => $method->type,
                    'amount' => $ship['amount'], 'tax_amount' => $ship['tax_amount'], 'estimated_days' => $ship['estimated_days'],
                    'carrier_code' => $ship['carrier_code'], 'zone_id' => $ship['zone_id'], 'zone_name' => $ship['zone_name'],
                    'chargeable_weight_g' => $ship['chargeable_weight_g'], 'pickup_address' => $ship['pickup_address'],
                ]);
                foreach ($discounts['promotions'] as $promotion) {
                    $order->adjustments()->create(['source' => 'promotion', 'source_id' => $promotion['id'], 'label' => $promotion['name'], 'amount' => $promotion['amount']]);
                }
                if ($coupon) {
                    $couponAmount = $discounts['coupon']['amount'] + $shippingDiscount;
                    $order->adjustments()->create([
                        'source' => 'coupon', 'source_id' => $coupon->id, 'label' => 'Cupón '.$coupon->code,
                        'target' => $coupon->type === 'free_shipping' ? 'shipping' : 'items', 'amount' => $couponAmount,
                    ]);
                    CouponRedemption::query()->create([
                        'coupon_id' => $coupon->id, 'order_id' => $order->id, 'customer_id' => $customer?->id, 'email' => $email, 'discount_amount' => $couponAmount,
                    ]);
                }
                $order->payments()->create([
                    'method_code' => $paymentMethod, 'amount' => $grandTotal, 'expires_at' => now()->addMinutes($minutes),
                    'gateway_order' => $paymentMethod === 'bank_transfer' ? null : $order->number.'-1',
                ]);
                Consent::query()->create([
                    'customer_id' => $customer?->id, 'email' => $order->email, 'purpose' => 'terms', 'granted' => true,
                    'source' => 'checkout', 'ip' => $ip, 'text_version' => (string) app(SettingsRepository::class)->get('legal', 'terms_version', '1'),
                ]);
                OrderEvents::record($order, 'placed', array_filter(['payment_method' => $paymentMethod, 'coupon' => $coupon?->code]), visible: true, to: 'pending', actor: $customer ? 'customer' : 'system');
                $cart->update(['status' => 'converted', 'email' => $order->email]);
                app(AuditLogger::class)->record('Order.placed', $order, [], ['number' => $order->number, 'total' => $grandTotal]);

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Dos envíos simultáneos con la misma clave: gana el primero
            if ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first()) {
                return $existing;
            }
            throw $e;
        } catch (ValidationException $e) {
            if (isset($e->errors()['stock'])) {
                throw new CheckoutException('stock_insufficient', 'Mientras comprabas, otra persona se llevó las últimas unidades de un producto. Revisa tu carrito.', 409, $this->carts->summary($cart->fresh()));
            }
            throw $e;
        }

        $order->refresh();
        // Con pasarela, los correos salen cuando el pago se confirma (OrderPayments::markPaid)
        if ($manual) {
            Mail::to($order->email)->queue(new OrderPlacedMail($order));
            if ($notify = app(SettingsRepository::class)->get('business', 'contact_email')) {
                Mail::to($notify)->queue(new NewOrderNotificationMail($order));
            }
        }

        return $order;
    }

    private function addressSnapshot(array $address, Region $region): array
    {
        return [
            'recipient' => $address['recipient'],
            'phone' => $address['phone'],
            'street' => $address['street'],
            'number' => $address['number'],
            'apartment' => $address['apartment'] ?? null,
            'commune' => $address['commune'],
            'region_code' => $region->code,
            'region' => $region->name,
            'reference' => $address['reference'] ?? null,
        ];
    }
}
