<?php

namespace Tests\Feature\Checkout;

use App\Domain\Customers\Mail\CustomerMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
    }

    private function register(string $email = 'ana@correo.cl'): string
    {
        return $this->flushHeaders()->postJson(self::API.'/auth/register', [
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => $email,
            'password' => 'clave1234', 'password_confirmation' => 'clave1234', 'accepts_terms' => true,
        ])->assertCreated()->json('data.token');
    }

    private function as(string $token): static
    {
        return $this->flushHeaders()->withHeaders(['Authorization' => "Bearer $token"]);
    }

    public function test_register_login_profile_and_addresses(): void
    {
        $token = $this->register();
        Mail::assertQueued(CustomerMail::class, fn ($m) => $m->kind === 'verify');
        $this->flushHeaders()->postJson(self::API.'/auth/register', ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'ANA@correo.cl', 'password' => 'clave1234', 'password_confirmation' => 'clave1234', 'accepts_terms' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->as($token)->getJson(self::API.'/me')->assertOk()->assertJsonPath('data.email', 'ana@correo.cl')->assertJsonPath('data.email_verified', false);
        $this->as($token)->patchJson(self::API.'/me', ['first_name' => 'Ana María', 'last_name' => 'Pérez', 'rut' => '11.111.111-2'])
            ->assertUnprocessable()->assertJsonValidationErrors(['rut']);

        $address = $this->as($token)->postJson(self::API.'/me/addresses', [
            'recipient' => 'Ana', 'phone' => '912345678', 'street' => 'Los Leones', 'number' => '100', 'region_code' => 'CL-RM', 'commune' => 'Providencia',
        ])->assertCreated()->json('data');
        $this->assertTrue($address['is_default'], 'La primera dirección queda por defecto');

        // Ingreso con contraseña incorrecta y bloqueo por intentos
        for ($i = 0; $i < 5; $i++) {
            $this->flushHeaders()->postJson(self::API.'/auth/login', ['email' => 'ana@correo.cl', 'password' => 'mala'])->assertUnprocessable();
        }
        $this->flushHeaders()->postJson(self::API.'/auth/login', ['email' => 'ana@correo.cl', 'password' => 'clave1234'])->assertStatus(429);

        $this->as($token)->postJson(self::API.'/auth/logout')->assertOk();
        $this->as($token)->getJson(self::API.'/me')->assertUnauthorized();
        $this->flushHeaders()->getJson(self::API.'/me')->assertUnauthorized();
    }

    public function test_guest_cart_joins_the_account_on_login_and_orders_are_private(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $this->register();
        $cartToken = $this->addToCart('TAZA-0', 2)->json('token');

        $login = $this->flushHeaders()->withHeaders(['X-Cart-Token' => $cartToken])
            ->postJson(self::API.'/auth/login', ['email' => 'ANA@correo.cl', 'password' => 'clave1234'])->assertOk()->json('data.token');
        $this->as($login)->getJson(self::API.'/cart')->assertJsonPath('data.count', 2);

        $order = $this->checkout($cartToken, $this->checkoutData(), 'c1', $login)->assertCreated()->json('data');
        $mine = $this->as($login)->getJson(self::API.'/me/orders')->assertOk()->json('data');
        $this->assertSame($order['number'], $mine[0]['number']);
        $this->as($login)->getJson(self::API.'/me/orders/'.$mine[0]['uuid'])->assertOk()->assertJsonPath('data.shipping.address.street', 'Av. Providencia');

        // Otro cliente no ve el pedido ajeno
        $other = $this->register('otro@correo.cl');
        $this->as($other)->getJson(self::API.'/me/orders/'.$mine[0]['uuid'])->assertNotFound();
        $this->as($other)->getJson(self::API.'/me/orders')->assertJsonCount(0, 'data');
    }

    public function test_verifying_email_links_previous_guest_orders(): void
    {
        $this->stockedProduct('Taza', 10000, 5);
        $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData(['email' => 'luis@correo.cl']), 'g');
        $this->register('luis@correo.cl');

        $url = null;
        Mail::assertQueued(CustomerMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return $m->kind === 'verify';
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->flushHeaders()->postJson(self::API.'/auth/verify-email', ['token' => 'falso'])->assertUnprocessable();
        $this->flushHeaders()->postJson(self::API.'/auth/verify-email', ['token' => $query['token']])->assertOk();

        $customer = Customer::query()->where('email', 'luis@correo.cl')->firstOrFail();
        $this->assertNotNull($customer->email_verified_at);
        $this->assertSame($customer->id, Order::query()->sole()->customer_id);
    }

    public function test_password_reset_without_revealing_accounts(): void
    {
        $this->register();
        $same = fn (string $email) => $this->flushHeaders()->postJson(self::API.'/auth/forgot-password', ['email' => $email])->assertOk()->json('data.message');
        $this->assertSame($same('ana@correo.cl'), $same('nadie@correo.cl'));

        $url = null;
        Mail::assertQueued(CustomerMail::class, function ($m) use (&$url) {
            if ($m->kind === 'reset') {
                $url = $m->url;
            }

            return $m->kind === 'reset';
        });
        $this->assertStringStartsWith(config('flux.storefront_url').'/cuenta/restablecer?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->flushHeaders()->postJson(self::API.'/auth/reset-password', ['token' => $q['token'], 'email' => $q['email'], 'password' => 'nueva12345', 'password_confirmation' => 'nueva12345'])->assertOk();
        $this->flushHeaders()->postJson(self::API.'/auth/login', ['email' => 'ana@correo.cl', 'password' => 'nueva12345'])->assertOk();
    }
}
