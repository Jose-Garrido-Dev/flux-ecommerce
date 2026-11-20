<?php

namespace Tests\Feature\Payments;

use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Mail\RefundMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Models\PaymentTransaction;
use App\Domain\Payments\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

class WebpayPaymentTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase, WebpayFixtures;

    private string $sku;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
        $this->enableWebpay();
        $this->sku = $this->stockedProduct('Polera', 12990, 3)->variants()->first()->sku;
    }

    /** Checkout con Webpay: devuelve el token de seguimiento del pedido. */
    private function placeWebpayOrder(): array
    {
        $cart = $this->addToCart($this->sku, 1)->json('token');
        $res = $this->checkout($cart, $this->checkoutData(['payment_method' => 'webpay_plus']), 'clave-'.uniqid())->assertCreated();
        $this->assertSame('pay', $res->json('data.next'));

        return [$res->json('data.token'), Order::query()->latest('id')->first()];
    }

    private function pay(string $token)
    {
        return $this->flushHeaders()->postJson(self::API."/orders/$token/pay");
    }

    public function test_webpay_is_offered_only_when_enabled_and_verified(): void
    {
        $codes = fn () => collect($this->flushHeaders()->getJson(self::API.'/checkout/options')->json('data.payment_methods'))->pluck('code')->all();
        $this->assertSame(['webpay_plus', 'bank_transfer'], $codes());
        $this->assertTrue($this->getJson(self::API.'/checkout/options')->json('data.payment_methods.0.test_mode'));

        // Cambiar las credenciales invalida la verificación anterior
        $gateway = PaymentGateway::for('webpay_plus');
        $gateway->credentials = ['commerce_code' => '597000000000', 'api_key' => 'otra'];
        $gateway->save();
        $this->assertSame(['bank_transfer'], $codes());

        $this->enableWebpay();
        PaymentGateway::for('webpay_plus')->update(['enabled' => false]);
        $this->assertSame(['bank_transfer'], $codes());

        $cart = $this->addToCart($this->sku, 1)->json('token');
        $this->checkout($cart, $this->checkoutData(['payment_method' => 'webpay_plus']))->assertStatus(422)->assertJsonPath('code', 'payment_unavailable');
    }

    public function test_approved_payment_marks_the_order_paid_once(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        Mail::assertNotQueued(OrderPlacedMail::class); // con pasarela, el correo sale al pagar
        $this->assertSame(1, (int) InventoryLevel::query()->sum('reserved'));

        $res = $this->pay($token)->assertOk();
        $tbk = $res->json('data.redirect.fields.token_ws');
        $this->assertSame('https://webpay3gint.transbank.cl/webpayserver/initTransaction', $res->json('data.redirect.url'));
        $payment = $order->latestPayment()->first();
        $this->assertSame($tbk, $payment->external_id);
        $this->assertSame($order->number.'-1', $payment->gateway_order);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['amount'] === $order->grand_total && $r['buy_order'] === $order->number.'-1'
            && $r->hasHeader('Tbk-Api-Key-Id', '597055555532') && str_ends_with($r['return_url'], '/api/pagos/webpay_plus/retorno'));

        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-1'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk])
            ->assertStatus(303)->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=aprobado");

        $order->refresh();
        $this->assertSame(['processing', 'paid'], [$order->status, $order->payment_status]);
        $this->assertSame('6623', $payment->fresh()->card_last4);
        $this->assertSame(0, (int) InventoryLevel::query()->sum('reserved'));
        $this->assertSame(2, (int) InventoryLevel::query()->sum('on_hand'));
        Mail::assertQueued(PaymentConfirmedMail::class, 1);
        Mail::assertQueued(NewOrderNotificationMail::class, 1);

        // Volver a la URL de retorno (recargar, doble envío) no tiene efecto doble ni vuelve a confirmar
        $this->get('/api/pagos/webpay_plus/retorno?token_ws='.$tbk)->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=aprobado");
        Http::assertSentCount(1);
        $this->assertSame(2, (int) InventoryLevel::query()->sum('on_hand'));
        Mail::assertQueued(PaymentConfirmedMail::class, 1);

        $this->flushHeaders()->getJson(self::API."/orders/$token")
            ->assertJsonPath('data.payment.method', 'Webpay')->assertJsonPath('data.payment.card_last4', '6623')
            ->assertJsonPath('data.payment.payment_type', 'Débito')->assertJsonPath('data.payment.can_pay', false);

        // Sin datos de tarjeta en el registro
        $this->assertStringNotContainsString('4051', PaymentTransaction::query()->get()->toJson());
    }

    public function test_amount_mismatch_is_never_marked_paid(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $tbk = $this->pay($token)->json('data.redirect.fields.token_ws');

        $this->fakeWebpay(commit: $this->approved(100, $order->number.'-1'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk])->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=rechazado");

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame('failed', $order->latestPayment()->first()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Order.payment_failed']);
    }

    public function test_rejected_payment_can_be_retried_with_a_new_buy_order(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $tbk = $this->pay($token)->json('data.redirect.fields.token_ws');

        $this->fakeWebpay(commit: $this->rejected($order->grand_total, $order->number.'-1'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk])->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=rechazado");
        $this->assertSame('pending', $order->fresh()->status);
        $this->flushHeaders()->getJson(self::API."/orders/$token")
            ->assertJsonPath('data.payment.status', 'failed')->assertJsonPath('data.payment.can_pay', true);
        $this->assertSame(1, (int) InventoryLevel::query()->sum('reserved'), 'El stock sigue apartado para reintentar');

        $this->fakeWebpay();
        $second = $this->pay($token)->assertOk()->json('data.redirect.fields.token_ws');
        $this->assertNotSame($tbk, $second);
        $this->assertSame([$order->number.'-1', $order->number.'-2'], Payment::query()->orderBy('id')->pluck('gateway_order')->all());
        $this->assertSame(['failed', 'pending'], Payment::query()->orderBy('id')->pluck('status')->all());

        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-2'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $second])->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=aprobado");
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->pay($token)->assertStatus(409)->assertJsonPath('code', 'order_not_payable');
    }

    public function test_aborted_and_timed_out_returns(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $tbk = $this->pay($token)->json('data.redirect.fields.token_ws');

        // El cliente presiona "Anular compra": vuelve con TBK_TOKEN
        $this->post('/api/pagos/webpay_plus/retorno', ['TBK_TOKEN' => $tbk, 'TBK_ORDEN_COMPRA' => $order->number.'-1', 'TBK_ID_SESION' => 'x'])
            ->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=anulado");
        $this->assertSame('aborted', $order->latestPayment()->first()->status);
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT');

        // Se acaba el tiempo en el formulario: solo vuelve la orden de compra
        $this->pay($token);
        $this->post('/api/pagos/webpay_plus/retorno', ['TBK_ORDEN_COMPRA' => $order->number.'-2', 'TBK_ID_SESION' => 'x'])
            ->assertRedirect(config('flux.storefront_url')."/pedido/$token?pago=anulado");
        $this->assertSame('aborted', $order->latestPayment()->first()->status);

        // Un token inventado no toca ningún pedido
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => 'inventado'])->assertRedirect(config('flux.storefront_url').'/pago/resultado?estado=desconocido');
    }

    public function test_reconciliation_recovers_a_payment_whose_customer_never_came_back(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $this->pay($token);

        // El cliente pagó y cerró el navegador. El commit ya se hizo (422) y el estado dice AUTHORIZED.
        $this->fakeWebpay(commit: null, status: $this->approved($order->grand_total, $order->number.'-1'));
        $this->travel(11)->minutes();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $this->assertSame('paid', $order->fresh()->payment_status);
        Mail::assertQueued(PaymentConfirmedMail::class, 1);
    }

    public function test_unpaid_webpay_order_expires_after_the_window(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $this->pay($token);

        // Nunca pagó: el commit falla y el estado sigue INITIALIZED
        $this->fakeWebpay(commit: null, status: ['status' => 'INITIALIZED', 'amount' => $order->grand_total]);
        $this->travel(31)->minutes();
        $this->artisan('orders:expire-unpaid')->assertSuccessful();

        $order->refresh();
        $this->assertSame(['cancelled', 'expired'], [$order->status, $order->payment_status]);
        $this->assertSame(0, (int) InventoryLevel::query()->sum('reserved'));
        $this->assertSame(3, (int) InventoryLevel::query()->sum('on_hand'));
    }

    public function test_refunds_partial_then_total_with_restock_rules(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $tbk = $this->pay($token)->json('data.redirect.fields.token_ws');
        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-1'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk]);
        $refunds = app(RefundService::class);

        $this->expectValidation(fn () => $refunds->refund($order, $order->grand_total + 1, 'Demasiado'), 'amount');
        $this->expectValidation(fn () => $refunds->refund($order, 1000, 'Parcial con stock', restock: true), 'restock');

        $refunds->refund($order, 1000, 'Producto con detalle');
        $order->refresh();
        $this->assertSame(['processing', 'partially_refunded', 1000], [$order->status, $order->payment_status, $order->refunded_total]);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/refunds') && $r['amount'] === 1000);

        $refunds->refund($order, $order->grand_total - 1000, 'Cliente se arrepintió', restock: true);
        $order->refresh();
        $this->assertSame(['cancelled', 'refunded'], [$order->status, $order->payment_status]);
        $this->assertSame(3, (int) InventoryLevel::query()->sum('on_hand'), 'Los productos vuelven al stock');
        Mail::assertQueued(RefundMail::class, 2);
        $this->expectValidation(fn () => $refunds->refund($order, 1, 'Otra vez'), 'amount');
    }

    public function test_rejected_refund_changes_nothing(): void
    {
        $this->fakeWebpay();
        [$token, $order] = $this->placeWebpayOrder();
        $tbk = $this->pay($token)->json('data.redirect.fields.token_ws');
        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-1'), refund: ['error_message' => 'Operation not allowed']);
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk]);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error_message' => 'Operation not allowed'], 422)]);

        $this->expectValidation(fn () => app(RefundService::class)->refund($order, 1000, 'x'), 'amount');
        $this->assertSame(0, $order->fresh()->refunded_total);
        $this->assertDatabaseCount('refunds', 0);
        $this->assertDatabaseHas('payment_transactions', ['type' => 'refund', 'result' => 'error']);
    }

    public function test_credentials_are_encrypted_and_hidden_from_the_audit_log(): void
    {
        $raw = DB::table('payment_gateways')->where('code', 'webpay_plus')->value('credentials');
        $this->assertStringNotContainsString('579B532A', $raw);
        $this->assertStringNotContainsString('579B532A', DB::table('audit_logs')->get()->toJson());
        $this->assertArrayNotHasKey('credentials', PaymentGateway::for('webpay_plus')->toArray());
    }

    public function test_verify_command_marks_webpay_verified_only_when_transbank_answers(): void
    {
        PaymentGateway::for('webpay_plus')->update(['enabled' => false, 'verified_at' => null]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['webpay3gint.transbank.cl/*' => Http::response(['error_message' => 'Not Authorized'], 401)]);
        $this->artisan('payments:verify', ['--integration' => true, '--enable' => true])->assertFailed();
        $this->assertFalse(PaymentGateway::for('webpay_plus')->isAvailable());

        $this->fakeWebpay();
        $this->artisan('payments:verify', ['--integration' => true, '--enable' => true])
            ->expectsOutputToContain('verificada en integración')->assertSuccessful();
        $this->assertTrue(PaymentGateway::for('webpay_plus')->isAvailable());
    }

    private function expectValidation(callable $fn, string $key): void
    {
        try {
            $fn();
            $this->fail("Se esperaba un error de validación en $key");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
