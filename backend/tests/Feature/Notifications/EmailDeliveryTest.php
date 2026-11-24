<?php

namespace Tests\Feature\Notifications;

use App\Domain\Customers\Mail\CustomerMail;
use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\Notifications\Services\EmailResender;
use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\OrderCancelledMail;
use App\Domain\Orders\Mail\OrderPlacedMail;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Mail\RefundMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Models\Refund;
use App\Domain\Returns\Mail\ReturnMail;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Mail\ShipmentMail;
use App\Domain\Shipping\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\Feature\Language\SpanishOnlyAdminTest;
use Tests\TestCase;

/** Fase 8: las 16 plantillas (todas en español) se envían de verdad (mailer array), quedan en el historial, se pueden desactivar, reintentar y reenviar. */
class EmailDeliveryTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareStore();
        $this->stockedProduct('Taza', 10000, 5);
        $number = $this->checkout($this->addToCart('TAZA-0', 1)->json('token'), $this->checkoutData())->assertCreated()->json('data.number');
        $this->order = Order::query()->where('number', $number)->firstOrFail();
        EmailLog::query()->delete();
        $this->sent()->flush();
    }

    private function sent(): ArrayTransport
    {
        return Mail::mailer('array')->getSymfonyTransport();
    }

    /** Un correo por plantilla, con datos reales guardados. @return array<string, Mailable> */
    private function mailables(): array
    {
        $order = $this->order;
        $item = $order->items()->firstOrFail();
        $shipment = Shipment::query()->create([
            'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'tracking_token_hash' => str_repeat('b', 64),
            'carrier_name' => 'Starken', 'tracking_number' => 'ABC123', 'status' => 'shipped',
        ]);
        $shipment->items()->create(['order_item_id' => $item->id, 'quantity' => 1]);
        $return = ReturnRequest::query()->create(['order_id' => $order->id, 'type' => 'return', 'reason' => 'size', 'status' => 'processed', 'resolution' => 'Reembolsamos $10.000.', 'rejection_reason' => 'Usado', 'instructions' => 'Envíala a la tienda.']);
        $return->items()->create(['order_item_id' => $item->id, 'quantity' => 1]);
        $refund = Refund::query()->create(['payment_id' => $order->payments()->value('id'), 'order_id' => $order->id, 'amount' => 10000, 'reason' => 'Devolución', 'status' => 'manual']);
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234']);

        return [
            'order_placed' => new OrderPlacedMail($order),
            'payment_confirmed' => new PaymentConfirmedMail($order),
            'order_cancelled' => new OrderCancelledMail($order, 'El plazo para pagar venció.'),
            'refund' => new RefundMail($order, $refund),
            'new_order_admin' => new NewOrderNotificationMail($order),
            'shipment_shipped' => new ShipmentMail($order, $shipment, 'shipped'),
            'shipment_ready_for_pickup' => new ShipmentMail($order, $shipment, 'ready_for_pickup'),
            'shipment_delivered' => new ShipmentMail($order, $shipment, 'delivered'),
            'shipment_picked_up' => new ShipmentMail($order, $shipment, 'picked_up'),
            'return_requested' => new ReturnMail($order, $return, 'requested'),
            'return_approved' => new ReturnMail($order, $return, 'approved'),
            'return_rejected' => new ReturnMail($order, $return, 'rejected'),
            'return_processed' => new ReturnMail($order, $return, 'processed'),
            'return_new_admin' => new ReturnMail($order, $return, 'new_admin'),
            'customer_verify' => new CustomerMail($customer, 'verify', 'https://tienda.test/cuenta/verificar'),
            'customer_reset' => new CustomerMail($customer, 'reset', 'https://tienda.test/cuenta/restablecer'),
        ];
    }

    public function test_every_template_is_sent_logged_and_uses_the_edited_text(): void
    {
        EmailTemplate::syncDefaults();
        EmailTemplate::query()->where('key', 'payment_confirmed')->update(['subject' => 'Pago listo, {nombre} — pedido #{pedido}', 'intro' => 'Gracias **{nombre}**.']);
        $mails = $this->mailables();
        $this->assertSame(array_keys(EmailTemplateRegistry::all()), array_keys($mails), 'Cada plantilla del registro tiene su correo');
        $this->assertCount(16, $mails);

        foreach ($mails as $mail) {
            Mail::to('ana@correo.cl')->queue($mail);
        }

        $this->assertCount(16, $this->sent()->messages());
        foreach ($this->sent()->messages() as $message) {
            $html = $message->getOriginalMessage()->getHtmlBody();
            $this->assertSame([], SpanishOnlyAdminTest::englishFragments($html), 'Correo en inglés: '.$message->getOriginalMessage()->getSubject());
            $this->assertStringNotContainsString('Laravel', $html);
        }
        $logs = EmailLog::query()->get()->keyBy('template_key');
        foreach (array_keys($mails) as $key) {
            $log = $logs[$key] ?? null;
            $this->assertNotNull($log, "Sin historial: $key");
            $this->assertSame('sent', $log->status, $key);
            $this->assertSame(1, $log->attempts, $key);
            $this->assertNotEmpty($log->subject, $key);
            $this->assertStringNotContainsString('{', $log->subject, "Variable sin reemplazar en $key");
        }
        $this->assertSame("Pago listo, Ana — pedido #{$this->order->number}", $logs['payment_confirmed']->subject);
        $this->assertSame($this->order->id, $logs['payment_confirmed']->order_id);
        $html = collect($this->sent()->messages())->first(fn (SentMessage $m) => str_contains($m->getOriginalMessage()->getSubject(), 'Pago listo'))->getOriginalMessage()->getHtmlBody();
        $this->assertMatchesRegularExpression('#Gracias <strong[^>]*>Ana</strong>#', $html);
    }

    public function test_a_disabled_template_is_skipped_but_required_ones_are_always_sent(): void
    {
        EmailTemplate::syncDefaults();
        EmailTemplate::query()->update(['enabled' => false]);
        $mails = $this->mailables();
        Mail::to('ana@correo.cl')->queue($mails['shipment_shipped']);
        Mail::to('ana@correo.cl')->queue($mails['customer_reset']);

        $this->assertSame('skipped', EmailLog::query()->where('template_key', 'shipment_shipped')->value('status'));
        $this->assertSame('sent', EmailLog::query()->where('template_key', 'customer_reset')->value('status'), 'Restablecer la clave no se puede desactivar');
        $this->assertCount(1, $this->sent()->messages());
    }

    public function test_failures_are_retried_with_backoff_recorded_and_can_be_resent(): void
    {
        config(['queue.default' => 'database']);
        Mail::extend('roto', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new RuntimeException('Conexión rechazada por el servidor de correo');
            }

            public function __toString(): string
            {
                return 'roto';
            }
        });
        config(['mail.mailers.roto' => ['transport' => 'roto'], 'mail.default' => 'roto']);
        app('mail.manager')->forgetMailers();

        Mail::to('ana@correo.cl')->queue(new PaymentConfirmedMail($this->order));
        $log = EmailLog::query()->firstOrFail();
        $this->assertSame('queued', $log->status);

        $work = fn () => Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
        $work();
        $log->refresh();
        $this->assertSame('retrying', $log->status);
        $this->assertSame(1, $log->attempts);
        $this->assertStringContainsString('Conexión rechazada', $log->last_error);

        $work(); // aún no pasa el minuto de espera
        $this->assertSame(1, $log->fresh()->attempts);
        $this->travel(61)->seconds();
        $work();
        $this->assertSame(2, $log->fresh()->attempts);
        $this->travel(301)->seconds();
        $work();
        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertSame(3, $log->attempts);
        $this->assertNotNull($log->failed_at);

        // Se arregla el servidor y se reenvía desde el historial: queda como un envío nuevo
        config(['mail.default' => 'array']);
        app('mail.manager')->forgetMailers();
        EmailResender::resend($log);
        $work();
        $this->assertSame('failed', $log->fresh()->status, 'El registro original no cambia');
        $resent = EmailLog::query()->whereKeyNot($log->id)->firstOrFail();
        $this->assertSame('sent', $resent->status);
        $this->assertSame('payment_confirmed', $resent->template_key);
        $this->assertCount(1, $this->sent()->messages());
    }

    public function test_variables_are_escaped_so_customer_data_cannot_inject_markup(): void
    {
        $this->assertSame('Hola \*\*Ana\*\* &lt;script&gt;', EmailTemplates::fill('Hola {nombre}', ['nombre' => '**Ana** <script>'], markdown: true));
        $this->assertSame('Pedido {desconocida}', EmailTemplates::fill('Pedido {desconocida}', [], markdown: true));
        $this->order->update(['first_name' => '<img src=x onerror=alert(1)> [clic](http://malo.test)']);
        $html = (new PaymentConfirmedMail($this->order->fresh()))->render();
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('href="http://malo.test"', $html);
    }

    public function test_restore_default_puts_back_the_original_text(): void
    {
        EmailTemplate::syncDefaults();
        $template = EmailTemplate::query()->where('key', 'order_placed')->firstOrFail();
        $original = $template->subject;
        $template->update(['subject' => 'Otro asunto']);
        $template->restoreDefault();
        $this->assertSame($original, $template->fresh()->subject);
        $this->assertSame(16, EmailTemplate::query()->count());
    }
}
