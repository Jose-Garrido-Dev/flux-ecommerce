<?php

namespace Tests\Feature\Admin;

use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\PaymentGateway;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\Feature\Payments\WebpayFixtures;
use Tests\TestCase;

class PaymentsAdminTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase, WebpayFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_webpay_is_configured_and_verified_from_the_panel(): void
    {
        $this->actingAs($this->user('super-admin'));
        $this->get('/admin/payment-settings')->assertOk()->assertSee('Webpay Plus (Transbank)')->assertSee('No verificada');

        $page = Livewire::test(PaymentSettingsPage::class)->callAction('webpayTestCredentials');
        $this->assertSame('597055555532', PaymentGateway::for('webpay_plus')->credential('commerce_code'));

        // Activar sin verificar: se guarda, pero no aparece en el checkout
        $page->set('data.webpay_enabled', true)->call('save')->assertHasNoErrors();
        $this->assertFalse(PaymentGateway::for('webpay_plus')->isAvailable());

        // Transbank rechaza: queda el error y sigue sin verificar
        Http::fake(['*' => Http::response(['error_message' => 'Not Authorized'], 401)]);
        $page->callAction('webpayVerify')->assertNotified('La conexión con Webpay falló');
        $this->assertFalse(PaymentGateway::for('webpay_plus')->isVerified());
        $this->assertStringContainsString('rechazó las credenciales', PaymentGateway::for('webpay_plus')->last_error);

        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['token' => 'abc', 'url' => 'https://webpay3gint.transbank.cl/webpayserver/initTransaction'])]);
        $page->callAction('webpayVerify')->assertNotified('Webpay verificada');
        $this->assertTrue(PaymentGateway::for('webpay_plus')->isAvailable());
        Http::assertSent(fn ($r) => $r['amount'] === 50 && $r->hasHeader('Tbk-Api-Key-Secret'));
        $this->assertSame('webpay_plus', $this->getJson(self::API.'/checkout/options')->json('data.payment_methods.0.code'));

        // Cambiar la llave invalida la verificación; la llave nunca vuelve al formulario
        $page->set('data.webpay_api_key', 'otra-llave')->call('save');
        $this->assertFalse(PaymentGateway::for('webpay_plus')->isAvailable());
        $this->assertNull($page->get('data.webpay_api_key'));
        $this->assertSame('otra-llave', PaymentGateway::for('webpay_plus')->credential('api_key'));
    }

    public function test_finance_refunds_a_webpay_order_and_checks_a_pending_payment(): void
    {
        $this->enableWebpay();
        $this->fakeWebpay();
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData(['payment_method' => 'webpay_plus']), uniqid())->json('data.token');
        $order = Order::query()->latest('id')->first();
        $this->postJson(self::API."/orders/$token/pay")->assertOk();

        $this->actingAs($this->user('finance'));
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Webpay')->assertSee('Orden de compra '.$order->number.'-1');

        // El cliente pagó pero no volvió: "Consultar en Webpay" lo deja pagado
        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-1'));
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHidden('confirmPayment')
            ->callAction('checkGateway')->assertNotified('El pago está aprobado: el pedido quedó pagado');
        $this->assertSame('paid', $order->fresh()->payment_status);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('refund', data: ['amount' => $order->grand_total + 1, 'reason' => 'x', 'restock' => false])
            ->assertHasActionErrors(['amount']);
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('refund', data: ['amount' => $order->grand_total, 'reason' => 'Sin stock en bodega', 'restock' => true])
            ->assertHasNoActionErrors();
        $order->refresh();
        $this->assertSame(['cancelled', 'refunded'], [$order->status, $order->payment_status]);
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Sin stock en bodega')->assertSee('Reembolsado');
    }

    public function test_sales_role_cannot_refund(): void
    {
        $this->enableWebpay();
        $this->fakeWebpay();
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData(['payment_method' => 'webpay_plus']), uniqid())->json('data.token');
        $order = Order::query()->latest('id')->first();
        $tbk = $this->postJson(self::API."/orders/$token/pay")->json('data.redirect.fields.token_ws');
        $this->fakeWebpay(commit: $this->approved($order->grand_total, $order->number.'-1'));
        $this->post('/api/pagos/webpay_plus/retorno', ['token_ws' => $tbk]);

        $this->actingAs($this->user('sales'));
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->assertActionHidden('refund');
    }
}
