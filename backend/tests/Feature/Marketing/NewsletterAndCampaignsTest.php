<?php

namespace Tests\Feature\Marketing;

use App\Domain\Cart\Services\CartService;
use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Marketing\Mail\NewsletterConfirmMail;
use App\Domain\Marketing\Models\Automation;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Services\AutomationRunner;
use App\Domain\Marketing\Services\CampaignService;
use App\Domain\Marketing\Services\NewsletterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

/** Fase 10 (criterio de aceptación): doble opt-in, y la baja se respeta en campañas y automatizaciones. */
class NewsletterAndCampaignsTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareStore();
        config(['mail.default' => 'array', 'flux.storefront_url' => 'https://tienda.test', 'app.url' => 'https://api.tienda.test']);
    }

    private function sent(): TransportInterface
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport();
    }

    /** Suscribe por la API y confirma con el enlace del correo real (mailer array). */
    private function subscribeAndConfirm(string $email, string $name = 'Ana'): NewsletterSubscriber
    {
        $this->postJson(self::API.'/newsletter', ['email' => $email, 'name' => $name, 'consent' => true])->assertStatus(202);
        $mail = collect($this->sent()->messages())->last(fn ($m) => $m->getOriginalMessage()->getTo()[0]->getAddress() === $email);
        $this->assertNotNull($mail, "Llegó el correo de confirmación a $email");
        $this->assertSame('Confirma tu suscripción · Tienda de Prueba', $mail->getOriginalMessage()->getSubject());
        preg_match('#https://tienda\.test/newsletter/confirmar\?token=([A-Za-z0-9]+)#', $mail->getOriginalMessage()->getHtmlBody(), $m);
        $this->postJson(self::API.'/newsletter/confirm', ['token' => $m[1]])->assertOk();
        $this->sent()->flush();

        return NewsletterSubscriber::query()->where('email', $email)->firstOrFail();
    }

    public function test_double_opt_in(): void
    {
        $this->postJson(self::API.'/newsletter', ['email' => 'Ana@Correo.cl', 'consent' => false])->assertStatus(422)
            ->assertJsonPath('errors.consent.0', 'Debes aceptar recibir correos de la tienda.');
        $this->postJson(self::API.'/newsletter', ['email' => 'Ana@Correo.cl', 'consent' => true])->assertStatus(202);
        $subscriber = NewsletterSubscriber::query()->firstOrFail();
        $this->assertSame(['ana@correo.cl', 'pending'], [$subscriber->email, $subscriber->status]);
        $this->assertCount(1, $this->sent()->messages());

        // Repetir de inmediato no reenvía (no sirve para enviar spam) y la respuesta es la misma
        $this->postJson(self::API.'/newsletter', ['email' => 'ana@correo.cl', 'consent' => true])->assertStatus(202)
            ->assertJsonPath('data.message', 'Te enviamos un correo para confirmar tu suscripción. Revisa tu bandeja de entrada (y la de correo no deseado).');
        $this->assertCount(1, $this->sent()->messages());

        $this->postJson(self::API.'/newsletter/confirm', ['token' => 'inventado'])->assertStatus(422);
        $confirmed = $this->subscribeAndConfirm('maria@correo.cl', 'María');
        $this->assertSame('subscribed', $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertTrue(Consent::query()->where('email', 'maria@correo.cl')->where('purpose', 'marketing')->where('granted', true)->where('source', 'newsletter_confirm')->exists());
    }

    public function test_confirmation_link_expires(): void
    {
        Mail::fake();
        $this->postJson(self::API.'/newsletter', ['email' => 'ana@correo.cl', 'consent' => true]);
        $url = null;
        Mail::assertQueued(NewsletterConfirmMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->travel(NewsletterService::CONFIRM_DAYS + 1)->days();
        $this->postJson(self::API.'/newsletter/confirm', ['token' => $q['token']])->assertStatus(422);
    }

    public function test_campaign_reaches_only_confirmed_subscribers_and_respects_unsubscribes(): void
    {
        $ana = $this->subscribeAndConfirm('ana@correo.cl');
        $luis = $this->subscribeAndConfirm('luis@correo.cl', 'Luis');
        $this->postJson(self::API.'/newsletter', ['email' => 'pendiente@correo.cl', 'consent' => true]);

        $campaign = Campaign::query()->create([
            'name' => 'Cyber', 'subject' => 'Cyber en {tienda}', 'heading' => 'Hola {nombre}', 'body' => "Hasta **40 %** de descuento.\n\n<script>x</script>",
            'button_label' => 'Ver ofertas', 'button_url' => '/catalogo?on_sale=1',
        ]);
        // Luis se da de baja con el enlace del correo después de programar y antes del envío
        app(CampaignService::class)->schedule($campaign, now()->addHour());
        $token = app(NewsletterService::class)->unsubscribeToken('luis@correo.cl');
        $this->postJson(self::API.'/newsletter/unsubscribe', ['token' => $token])->assertOk();
        $this->assertSame('unsubscribed', $luis->fresh()->status);

        $this->sent()->flush();
        $this->travel(61)->minutes();
        $this->artisan('marketing:send-due-campaigns')->assertSuccessful();
        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->recipients_count);
        $this->assertSame(1, $campaign->sent_count);

        $messages = $this->sent()->messages();
        $this->assertCount(1, $messages);
        $email = $messages[0]->getOriginalMessage();
        $this->assertSame('ana@correo.cl', $email->getTo()[0]->getAddress());
        $this->assertSame('Cyber en Tienda de Prueba', $email->getSubject());
        $html = $email->getHtmlBody();
        $this->assertStringContainsString('Hola Ana', $html);
        $this->assertMatchesRegularExpression('#<strong[^>]*>40 %</strong>#', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('https://tienda.test/newsletter/baja?token=', $html, 'Enlace de baja visible');
        $this->assertStringContainsString('<https://api.tienda.test/api/storefront/v1/newsletter/baja/', $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());

        // Clic: se registra y redirige al enlace guardado, nunca a uno de la URL
        preg_match('#/api/storefront/v1/newsletter/clic/([0-9]+\.[A-Za-z0-9_-]+)#', $html, $m);
        $this->get(self::API.'/newsletter/clic/'.$m[1])->assertRedirect('https://tienda.test/catalogo?on_sale=1');
        $this->get(self::API.'/newsletter/clic/'.$m[1].'x')->assertNotFound();
        $this->assertSame(1, $campaign->fresh()->clicks_count);

        // Baja en un clic desde el programa de correo (RFC 8058)
        $this->post(self::API.'/newsletter/baja/'.app(NewsletterService::class)->unsubscribeToken('ana@correo.cl'), ['List-Unsubscribe' => 'One-Click'])->assertOk();
        $this->assertSame('unsubscribed', $ana->fresh()->status);
        $this->assertFalse(Consent::query()->where('email', 'ana@correo.cl')->latest('id')->first()->granted);
    }

    public function test_unsubscribe_during_sending_is_respected(): void
    {
        $this->subscribeAndConfirm('ana@correo.cl');
        $luis = $this->subscribeAndConfirm('luis@correo.cl');
        config(['queue.default' => 'database']);
        $campaign = Campaign::query()->create(['name' => 'Novedades', 'subject' => 'Novedades', 'heading' => 'Novedades', 'body' => 'Texto']);
        app(CampaignService::class)->schedule($campaign);
        $this->assertSame('sending', $campaign->fresh()->status);
        app(NewsletterService::class)->unsubscribe($luis, 'enlace');

        $this->sent()->flush();
        $this->artisan('queue:work', ['--once' => true, '--queue' => 'default'])->assertSuccessful();
        $campaign->refresh();
        $this->assertSame(['sent', 1, 1], [$campaign->status, $campaign->sent_count, $campaign->skipped_count]);
        $this->assertCount(1, $this->sent()->messages());
    }

    public function test_campaign_audiences_and_validation(): void
    {
        $this->subscribeAndConfirm('ana@correo.cl');
        $campaign = Campaign::query()->create(['name' => 'Compradores', 'subject' => 'Gracias', 'heading' => 'Gracias', 'body' => 'Texto', 'audience' => 'buyers']);
        $this->assertThrows(fn () => app(CampaignService::class)->schedule($campaign), ValidationException::class, 'No hay suscritos confirmados');
        $campaign->update(['audience' => 'all', 'button_label' => 'Ir', 'button_url' => 'javascript:alert(1)']);
        $this->assertThrows(fn () => app(CampaignService::class)->schedule($campaign), ValidationException::class, 'ruta de la tienda');
    }

    public function test_automations_only_write_to_subscribers_once_and_after_activation(): void
    {
        AutomationRunner::seedDefaults();
        $before = $this->subscribeAndConfirm('antigua@correo.cl');
        $this->travel(1)->minutes();
        Automation::query()->where('trigger', 'welcome')->update(['is_active' => true, 'active_since' => now()]);
        $this->travel(1)->minutes();
        $this->subscribeAndConfirm('nueva@correo.cl');

        // Carrito abandonado de un cliente suscrito, y otro de un cliente sin suscripción
        $customer = Customer::query()->create(['first_name' => 'Nueva', 'last_name' => 'P', 'email' => 'nueva@correo.cl', 'password' => 'clave1234']);
        $other = Customer::query()->create(['first_name' => 'Otro', 'last_name' => 'P', 'email' => 'otro@correo.cl', 'password' => 'clave1234']);
        Automation::query()->where('trigger', 'abandoned_cart')->update(['is_active' => true, 'active_since' => now()->subMinute()]);
        $product = $this->stockedProduct('Polera', 10000, 10);
        foreach ([$customer, $other] as $c) {
            [$cart] = app(CartService::class)->create($c);
            app(CartService::class)->setQuantity($cart, $product->variants()->first()->sku, 2);
        }

        $this->sent()->flush();
        $this->travel(5)->hours();
        $this->artisan('marketing:run-automations')->assertSuccessful();
        $to = collect($this->sent()->messages())->map(fn ($m) => [$m->getOriginalMessage()->getTo()[0]->getAddress(), $m->getOriginalMessage()->getSubject()])->all();
        $this->assertEqualsCanonicalizing([
            ['nueva@correo.cl', 'Bienvenida a Tienda de Prueba'],
            ['nueva@correo.cl', 'Dejaste productos en tu carrito'],
        ], $to, 'Ni la suscriptora antigua ni el cliente sin suscripción reciben nada');
        $cartMail = collect($this->sent()->messages())->first(fn ($m) => str_contains($m->getOriginalMessage()->getSubject(), 'carrito'));
        $this->assertStringContainsString('Polera', $cartMail->getOriginalMessage()->getHtmlBody());

        // Una segunda pasada no repite
        $this->artisan('marketing:run-automations')->assertSuccessful();
        $this->assertCount(2, $this->sent()->messages());
    }

    public function test_account_opt_in_and_out_sync_with_the_newsletter(): void
    {
        Mail::fake();
        $register = $this->postJson(self::API.'/auth/register', [
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234', 'password_confirmation' => 'clave1234',
            'accepts_terms' => true, 'marketing_opt_in' => true,
        ])->assertCreated();
        $this->assertSame('pending', NewsletterSubscriber::query()->where('email', 'ana@correo.cl')->value('status'), 'Se confirma al verificar el correo');
        $customer = Customer::query()->where('email', 'ana@correo.cl')->firstOrFail();
        app(NewsletterService::class)->customerVerified(tap($customer)->forceFill(['email_verified_at' => now()])->save() ? $customer->fresh() : $customer);
        $this->assertSame('subscribed', NewsletterSubscriber::query()->where('email', 'ana@correo.cl')->value('status'));

        $this->withToken($register->json('data.token'))->patchJson(self::API.'/me', ['first_name' => 'Ana', 'last_name' => 'Pérez', 'marketing_opt_in' => false])->assertOk();
        $this->assertSame('unsubscribed', NewsletterSubscriber::query()->where('email', 'ana@correo.cl')->value('status'));
    }

    public function test_checkout_checkbox_subscribes_with_double_opt_in(): void
    {
        Mail::fake();
        $sku = $this->stockedProduct('Polera', 10000, 10)->variants()->first()->sku;
        $token = $this->addToCart($sku, 1)->json('token');
        $this->checkout($token, $this->checkoutData(['subscribe_newsletter' => true]))->assertCreated();
        $this->assertSame('pending', NewsletterSubscriber::query()->where('email', 'ana@correo.cl')->value('status'));
        Mail::assertQueued(NewsletterConfirmMail::class);
    }
}
