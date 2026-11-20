<?php

namespace Tests\Feature\Checkout;

use App\Domain\Cart\Models\Cart;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Criterio de aceptación de la Fase 5: checkout invitado de punta a punta con copia fija y total manipulado ignorado. */
class CheckoutApiTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
    }

    private function level(string $sku): InventoryLevel
    {
        return InventoryLevel::query()->whereHas('variant', fn ($q) => $q->where('sku', $sku))->firstOrFail();
    }

    public function test_guest_checkout_end_to_end_creates_a_pending_order_with_a_correct_snapshot(): void
    {
        $taza = $this->stockedProduct('Taza', 11900, 5);
        $polera = $this->stockedProduct('Polera', 15000, 3, sale: 12990);

        // Carrito de invitado: el primer agregado entrega el token
        $first = $this->addToCart('TAZA-0', 2)->assertCreated();
        $token = $first->json('token');
        $this->assertNotEmpty($token);
        $cart = $this->addToCart('POLERA-0', 1, $token)->assertCreated()->json('data');
        $this->assertSame(2 * 11900 + 12990, $cart['subtotal']);
        $this->assertSame(3, $cart['count']);
        $this->assertArrayNotHasKey('unit_cost', $cart['items'][0], 'Nunca se expone el costo');

        // Opciones de envío y pago para la región
        $options = $this->flushHeaders()->withHeaders(['X-Cart-Token' => $token])->getJson(self::API.'/checkout/options?region_code=CL-RM')->assertOk()->json('data');
        $this->assertSame(['domicilio'], array_column($options['shipping_methods'], 'code'), 'El retiro viene desactivado');
        $this->assertSame('bank_transfer', $options['payment_methods'][0]['code']);

        // El navegador intenta pagar menos: precios y totales manipulados se ignoran
        $expected = 2 * 11900 + 12990 + 3990;
        $response = $this->checkout($token, $this->checkoutData([
            'grand_total' => 1, 'subtotal' => 1, 'items' => [['sku' => 'TAZA-0', 'unit_price' => 1, 'quantity' => 2]],
            'shipping_total' => 0, 'expected_total' => $expected,
        ]))->assertCreated();
        $data = $response->json('data');
        $this->assertSame($expected, $data['grand_total']);

        $order = Order::query()->where('number', $data['number'])->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('ana@correo.cl', $order->email);
        $this->assertSame(2 * 11900 + 12990, $order->subtotal);
        $this->assertSame(3990, $order->shipping_total);
        $this->assertSame($expected, $order->grand_total);
        // IVA por línea: 23.800 → 3.800; 12.990 → 2.074; envío 3.990 → 637
        $this->assertSame(3800 + 2074 + 637, $order->tax_total);
        $this->assertSame('Av. Providencia', $order->shipping_address['street']);
        $this->assertSame('Metropolitana de Santiago', $order->shipping_address['region']);

        $taza_line = $order->items->firstWhere('sku', 'TAZA-0');
        $this->assertSame('Taza', $taza_line->product_name);
        $this->assertSame(11900, $taza_line->unit_price);
        $this->assertSame(23800, $taza_line->line_total);
        $this->assertSame(1900, $taza_line->tax_rate_bp);
        $this->assertSame(12990, $order->items->firstWhere('sku', 'POLERA-0')->unit_price, 'Usa el precio de oferta vigente');

        // Stock reservado, no descontado; carrito convertido; pago pendiente con plazo
        $this->assertSame(2, $this->level('TAZA-0')->reserved);
        $this->assertSame(5, $this->level('TAZA-0')->on_hand);
        $this->assertSame('converted', Cart::query()->sole()->status);
        $this->assertSame(48, (int) round(now()->diffInHours($order->latestPayment->expires_at)));
        $this->assertSame(['Pedido creado'], $order->events->pluck('type')->map(fn ($t) => 'Pedido creado')->all());
        Mail::assertQueued(OrderPlacedMail::class, fn ($m) => $m->hasTo('ana@correo.cl'));
        Mail::assertQueued(NewOrderNotificationMail::class, fn ($m) => $m->hasTo('ventas@tienda.test'));

        // La copia fija no cambia si después cambia el catálogo
        $taza->update(['name' => 'Taza nueva']);
        $taza->variants()->first()->priceIn('CLP')->update(['price_amount' => 99990]);
        $this->assertSame('Taza', $taza_line->fresh()->product_name);
        $this->assertSame(11900, $taza_line->fresh()->unit_price);

        // Reintento con la misma clave: mismo pedido, sin duplicar nada
        $this->checkout($token, $this->checkoutData())->assertOk()->assertJsonPath('data.number', $order->number);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(2, $this->level('TAZA-0')->reserved);

        // Seguimiento por token: sin datos personales completos
        $tracking = $this->flushHeaders()->getJson(self::API.'/orders/'.$data['token'])->assertOk()->json('data');
        $this->assertSame($order->number, $tracking['number']);
        $this->assertNull($tracking['email']);
        $this->assertSame(['commune' => 'Providencia', 'region' => 'Metropolitana de Santiago'], $tracking['shipping']['address']);
        $this->assertSame('123456789', $tracking['payment']['transfer']['account_number']);
        $this->flushHeaders()->getJson(self::API.'/orders/token-falso')->assertNotFound();
    }

    public function test_changed_total_returns_price_changed_without_creating_the_order(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->addToCart('TAZA-0', 1)->json('token');

        $this->checkout($token, $this->checkoutData(['expected_total' => 5000]))
            ->assertStatus(409)->assertJsonPath('code', 'price_changed')->assertJsonPath('grand_total', null);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, StockReservation::query()->count());
    }

    public function test_validation_and_unavailable_options(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->addToCart('TAZA-0', 1)->json('token');

        $this->checkout($token, ['email' => 'no-es-correo'])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'first_name', 'address.street', 'accepts_terms']);
        $this->checkout($token, $this->checkoutData(['shipping_method' => 'retiro']), 'k2')
            ->assertUnprocessable()->assertJsonPath('code', 'shipping_unavailable');
        $this->checkout($token, $this->checkoutData(['payment_method' => 'webpay']), 'k3')
            ->assertUnprocessable()->assertJsonPath('code', 'payment_unavailable');
        $this->checkout($token, $this->checkoutData(['document_type' => 'factura']), 'k4')
            ->assertUnprocessable()->assertJsonValidationErrors(['billing_rut', 'billing_name', 'billing_activity']);
        $this->flushHeaders()->withHeaders(['X-Cart-Token' => $token])->postJson(self::API.'/checkout', $this->checkoutData())
            ->assertStatus(400)->assertJsonPath('code', 'idempotency_key_required');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_cart_respects_stock_and_hides_unpublished_products(): void
    {
        $this->stockedProduct('Taza', 10000, 2);
        $this->addToCart('TAZA-0', 3)->assertUnprocessable()->assertJsonValidationErrors(['quantity']);
        $token = $this->addToCart('TAZA-0', 2)->json('token');
        $this->addToCart('TAZA-0', 1, $token)->assertUnprocessable();
        $this->flushHeaders()->withHeaders(['X-Cart-Token' => $token])->patchJson(self::API.'/cart/items/TAZA-0', ['quantity' => 1])
            ->assertOk()->assertJsonPath('data.count', 1);
        $this->flushHeaders()->withHeaders(['X-Cart-Token' => $token])->deleteJson(self::API.'/cart/items/TAZA-0')
            ->assertOk()->assertJsonPath('data.count', 0);

        $draft = $this->product('Borrador', [['price' => 1000]], ['status' => 'draft']);
        $this->addToCart($draft->variants()->first()->sku, 1)->assertUnprocessable()->assertJsonValidationErrors(['sku']);
        $this->addToCart('NO-EXISTE', 1)->assertUnprocessable();
    }

    public function test_last_unit_taken_by_another_buyer_rolls_back_the_order(): void
    {
        $this->stockedProduct('Taza', 10000, 1);
        $a = $this->addToCart('TAZA-0', 1)->json('token');
        $b = $this->addToCart('TAZA-0', 1)->json('token');

        $this->checkout($a, $this->checkoutData(), 'a')->assertCreated();
        $this->checkout($b, $this->checkoutData(['email' => 'otro@correo.cl']), 'b')
            ->assertStatus(409)->assertJsonPath('code', 'cart_changed');
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, $this->level('TAZA-0')->reserved);
    }

    public function test_confirming_the_transfer_discounts_stock_once_and_expiry_releases_it(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $paid = $this->checkout($this->addToCart('TAZA-0', 2)->json('token'), $this->checkoutData(), 'p')->json('data');
        $unpaid = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData(), 'u')->json('data');
        $this->assertSame(3, $this->level('TAZA-0')->reserved);

        $order = Order::query()->where('number', $paid['number'])->firstOrFail();
        $payments = app(OrderPayments::class);
        $this->assertTrue($payments->confirm($order, $order->grand_total, 'Op 555'));
        $this->assertFalse($payments->confirm($order, $order->grand_total), 'Confirmar dos veces no descuenta dos veces');
        $order->refresh();
        $this->assertSame('processing', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(3, $this->level('TAZA-0')->on_hand);
        $this->assertSame(1, $this->level('TAZA-0')->reserved);
        Mail::assertQueued(PaymentConfirmedMail::class, 1);

        // El otro vence: se cancela y libera su unidad
        $this->travel(49)->hours();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $expired = Order::query()->where('number', $unpaid['number'])->firstOrFail();
        $this->assertSame('cancelled', $expired->status);
        $this->assertSame('expired', $expired->payment_status);
        $this->assertSame(0, $this->level('TAZA-0')->reserved);
        $this->assertSame(3, $this->level('TAZA-0')->on_hand);
        $this->flushHeaders()->getJson(self::API.'/orders/'.$unpaid['token'])->assertJsonPath('data.payment_status_label', 'Vencido');
    }

    public function test_wrong_amount_cannot_confirm(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $data = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData(), 'w')->json('data');
        $order = Order::query()->where('number', $data['number'])->firstOrFail();

        $this->expectException(ValidationException::class);
        app(OrderPayments::class)->confirm($order, 100);
    }
}
