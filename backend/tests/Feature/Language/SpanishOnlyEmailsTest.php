<?php

namespace Tests\Feature\Language;

use App\Domain\Customers\Mail\CustomerMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Mail\RefundMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\System\Services\SettingsRepository;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Los correos que envía el sistema (plantilla de Laravel) también van en español. */
class SpanishOnlyEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_email_is_in_spanish(): void
    {
        ResetPassword::createUrlUsing(fn () => 'https://tienda.test/restablecer');
        $user = User::factory()->create();
        $html = (string) (new ResetPassword('token'))->toMail($user)->render();

        $this->assertStringContainsString('¡Hola!', $html);
        $this->assertSame([], SpanishOnlyAdminTest::englishFragments($html));
    }

    public function test_store_emails_are_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(SettingsRepository::class)->putMany('business', ['store_name' => 'Tienda Flux']);
        $order = Order::query()->create([
            'email' => 'ana@correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'subtotal' => 10000, 'tax_total' => 1597,
            'shipping_total' => 0, 'grand_total' => 10000, 'tracking_token_hash' => str_repeat('a', 64), 'placed_at' => now(),
        ]);
        $order->items()->create(['product_name' => 'Taza', 'sku' => 'TAZA-0', 'quantity' => 1, 'unit_price' => 10000, 'tax_rate_bp' => 1900, 'tax_amount' => 1597, 'line_total' => 10000]);
        $order->shippingLine()->create(['method_code' => 'retiro', 'method_name' => 'Retiro en tienda', 'type' => 'pickup', 'amount' => 0, 'tax_amount' => 0]);
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234']);

        $mails = [
            new OrderPlacedMail($order),
            new PaymentConfirmedMail($order),
            new NewOrderNotificationMail($order),
            new RefundMail($order, new Refund(['amount' => 1000, 'reason' => 'Demora'])),
            new CustomerMail($customer, 'verify', 'https://tienda.test/cuenta/verificar'),
            new CustomerMail($customer, 'reset', 'https://tienda.test/cuenta/restablecer'),
        ];
        foreach ($mails as $mail) {
            $html = $mail->render();
            $this->assertSame([], SpanishOnlyAdminTest::englishFragments($html), $mail::class);
            $this->assertStringNotContainsString('Laravel', $html);
        }
        $this->assertStringContainsString('Recibimos tu pedido', $mails[0]->envelope()->subject);
    }

    public function test_http_error_pages_are_in_spanish(): void
    {
        $this->get('/no-existe-esta-pagina')->assertNotFound()->assertSee('No encontrado');
    }
}
