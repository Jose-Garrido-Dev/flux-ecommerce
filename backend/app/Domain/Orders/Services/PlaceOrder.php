<?php

namespace App\Domain\Orders\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Services\ShippingQuoter;
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
        private readonly ShippingQuoter $shipping,
        private readonly PaymentSettings $payments,
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

        $summary = $this->carts->summary($cart);
        $lines = array_values(array_filter($summary['items'], fn ($l) => $l['purchasable']));
        if ($summary['items'] === []) {
            throw new CheckoutException('cart_empty', 'Tu carrito está vacío.', 422);
        }
        if (count($lines) !== count($summary['items'])) {
            throw new CheckoutException('cart_changed', 'Algunos productos de tu carrito ya no están disponibles. Revisa el carrito.', 409, $summary);
        }

        if (($input['payment_method'] ?? null) !== 'bank_transfer' || ! $this->payments->transferEnabled()) {
            throw new CheckoutException('payment_unavailable', 'El medio de pago elegido no está disponible.', 422);
        }

        $region = Region::query()->where('code', $input['address']['region_code'] ?? '')->where('is_enabled', true)->first();
        $method = ShippingMethod::query()->active()->where('code', $input['shipping_method'] ?? '')->first();
        $needsAddress = $method?->type !== 'pickup';
        if (! $method || ($needsAddress && (! $region || ! $method->servesRegion($region->id)))) {
            throw new CheckoutException('shipping_unavailable', 'El método de envío elegido no está disponible para tu dirección.', 422);
        }

        $subtotal = array_sum(array_column($lines, 'line_total'));
        $ship = $this->shipping->present($method, $subtotal);
        $itemsTax = array_sum(array_column($lines, 'tax_amount'));
        $grandTotal = $subtotal + $ship['amount'];

        // El total que vio el cliente solo sirve para avisarle si algo cambió; nunca se usa para cobrar
        if (isset($input['expected_total']) && (int) $input['expected_total'] !== $grandTotal) {
            throw new CheckoutException('price_changed', 'Los precios o el envío cambiaron. Revisa el nuevo total antes de confirmar.', 409, [
                ...$summary, 'shipping' => $ship, 'grand_total' => $grandTotal,
            ]);
        }

        $uuid = (string) Str::uuid();
        $token = OrderTokens::for($uuid);
        $hours = $this->payments->transferHours();

        try {
            $order = DB::transaction(function () use ($cart, $input, $customer, $idempotencyKey, $uuid, $token, $lines, $subtotal, $ship, $itemsTax, $grandTotal, $method, $region, $needsAddress, $hours, $ip) {
                $order = new Order([
                    'idempotency_key' => $idempotencyKey,
                    'customer_id' => $customer?->id,
                    'cart_id' => $cart->id,
                    'email' => Customer::normalizeEmail($input['email']),
                    'phone' => $input['phone'],
                    'first_name' => $input['first_name'],
                    'last_name' => $input['last_name'],
                    'subtotal' => $subtotal,
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
                        'tax_rate_bp' => $line['tax_rate_bp'],
                        'tax_amount' => $line['tax_amount'],
                        'line_total' => $line['line_total'],
                    ]);
                    // Reserva atómica: si otra compra se llevó la última unidad, falla y se deshace todo el pedido
                    $this->inventory->reserve($line['variant_id'], $line['quantity'], 'order', $uuid, $hours * 60);
                }

                $order->shippingLine()->create([
                    'method_id' => $method->id, 'method_code' => $method->code, 'method_name' => $method->name, 'type' => $method->type,
                    'amount' => $ship['amount'], 'tax_amount' => $ship['tax_amount'], 'estimated_days' => $method->estimated_days,
                ]);
                $order->payments()->create([
                    'method_code' => 'bank_transfer', 'amount' => $grandTotal, 'expires_at' => now()->addHours($hours),
                ]);
                Consent::query()->create([
                    'customer_id' => $customer?->id, 'email' => $order->email, 'purpose' => 'terms', 'granted' => true,
                    'source' => 'checkout', 'ip' => $ip, 'text_version' => (string) app(SettingsRepository::class)->get('legal', 'terms_version', '1'),
                ]);
                OrderEvents::record($order, 'placed', ['payment_method' => 'bank_transfer'], visible: true, to: 'pending', actor: $customer ? 'customer' : 'system');
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
        Mail::to($order->email)->queue(new OrderPlacedMail($order));
        if ($notify = app(SettingsRepository::class)->get('business', 'contact_email')) {
            Mail::to($notify)->queue(new NewOrderNotificationMail($order));
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
