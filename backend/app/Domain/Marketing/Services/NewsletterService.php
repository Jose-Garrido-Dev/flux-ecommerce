<?php

namespace App\Domain\Marketing\Services;

use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Marketing\Mail\NewsletterConfirmMail;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Newsletter con doble opt-in: suscribirse deja la dirección "por confirmar" y envía un enlace;
 * solo al confirmar se pueden enviar campañas. La baja es inmediata y queda registrada como consentimiento retirado.
 * Las respuestas públicas son siempre iguales para no revelar qué correos están suscritos.
 */
class NewsletterService
{
    public const CONFIRM_DAYS = 7;

    /** No se reenvía el correo de confirmación antes de este plazo (evita usar el formulario para enviar spam). */
    public const RESEND_MINUTES = 10;

    public function subscribe(string $email, ?string $name, string $source, ?string $ip = null, ?Customer $customer = null): NewsletterSubscriber
    {
        $email = Customer::normalizeEmail($email);

        return DB::transaction(function () use ($email, $name, $source, $ip, $customer) {
            $subscriber = NewsletterSubscriber::query()->lockForUpdate()->firstOrNew(['email' => $email]);
            if ($subscriber->exists && $subscriber->status === 'subscribed') {
                return $subscriber;
            }
            if ($subscriber->exists && $subscriber->status === 'pending' && $subscriber->confirm_sent_at?->gt(now()->subMinutes(self::RESEND_MINUTES))) {
                return $subscriber;
            }
            $token = Str::random(48);
            $subscriber->fill([
                'name' => $name ?: $subscriber->name,
                'source' => $subscriber->exists ? $subscriber->source : $source,
                'customer_id' => $customer?->id ?? $subscriber->customer_id ?? Customer::query()->where('email', $email)->value('id'),
                'status' => 'pending',
                'confirm_token_hash' => hash('sha256', $token),
                'confirm_sent_at' => now(),
                'unsubscribed_at' => null,
            ])->save();
            Consent::query()->create(['customer_id' => $subscriber->customer_id, 'email' => $email, 'purpose' => 'marketing', 'granted' => true, 'source' => "newsletter_$source", 'ip' => $ip, 'text_version' => 'por_confirmar']);
            Mail::to($email)->queue(new NewsletterConfirmMail($subscriber, $this->storefrontUrl('/newsletter/confirmar?token='.$token)));

            return $subscriber;
        });
    }

    /** Confirma con el token del correo. Devuelve null si el enlace no es válido o venció. */
    public function confirm(string $token, ?string $ip = null): ?NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()->where('confirm_token_hash', hash('sha256', $token))->where('status', 'pending')->first();
        if (! $subscriber || $subscriber->confirm_sent_at?->lt(now()->subDays(self::CONFIRM_DAYS))) {
            return null;
        }
        $this->markSubscribed($subscriber, 'newsletter_confirm', $ip);

        return $subscriber;
    }

    /**
     * Cuenta de cliente: con el correo ya verificado la suscripción queda confirmada de inmediato;
     * si no, queda pendiente y se confirma cuando el cliente verifique su correo.
     */
    public function syncCustomer(Customer $customer, bool $optIn, string $source, ?string $ip = null): void
    {
        $subscriber = NewsletterSubscriber::query()->where('email', $customer->email)->first();
        if (! $optIn) {
            if ($subscriber && $subscriber->status !== 'unsubscribed') {
                $this->unsubscribe($subscriber, 'cuenta', $ip);
            }

            return;
        }
        if ($subscriber?->status === 'subscribed') {
            return;
        }
        $subscriber ??= NewsletterSubscriber::query()->create(['email' => $customer->email, 'name' => $customer->first_name, 'customer_id' => $customer->id, 'source' => $source, 'status' => 'pending']);
        if ($customer->email_verified_at) {
            $this->markSubscribed($subscriber, $source, $ip);
        } else {
            $subscriber->update(['status' => 'pending', 'customer_id' => $customer->id, 'confirm_sent_at' => now(), 'unsubscribed_at' => null]);
        }
    }

    /** Al verificar su correo, el cliente que pidió el newsletter al registrarse queda suscrito. */
    public function customerVerified(Customer $customer): void
    {
        $subscriber = NewsletterSubscriber::query()->where('email', $customer->email)->where('status', 'pending')->first();
        if ($subscriber && $customer->marketing_opt_in) {
            $this->markSubscribed($subscriber, 'email_verified', null);
        }
    }

    public function unsubscribe(NewsletterSubscriber $subscriber, string $reason, ?string $ip = null): void
    {
        DB::transaction(function () use ($subscriber, $reason, $ip) {
            $subscriber->update(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'unsubscribe_reason' => $reason, 'confirm_token_hash' => null]);
            Consent::query()->create(['customer_id' => $subscriber->customer_id, 'email' => $subscriber->email, 'purpose' => 'marketing', 'granted' => false, 'source' => "newsletter_baja_$reason", 'ip' => $ip]);
            Customer::query()->where('email', $subscriber->email)->update(['marketing_opt_in' => false]);
        });
    }

    /** Enlace de baja sin vencimiento: correo firmado con la clave de la aplicación. */
    public function unsubscribeToken(string $email): string
    {
        $payload = rtrim(strtr(base64_encode(Customer::normalizeEmail($email)), '+/', '-_'), '=');

        return $payload.'.'.$this->sign('baja|'.$payload);
    }

    public function fromUnsubscribeToken(string $token): ?NewsletterSubscriber
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || ! hash_equals($this->sign('baja|'.$payload), $sig)) {
            return null;
        }
        $email = base64_decode(strtr($payload, '-_', '+/'), true);

        return $email ? NewsletterSubscriber::query()->where('email', $email)->first() : null;
    }

    public function unsubscribeUrl(string $email): string
    {
        return $this->storefrontUrl('/newsletter/baja?token='.$this->unsubscribeToken($email));
    }

    /** Dirección para la baja en un clic (cabecera List-Unsubscribe-Post, RFC 8058): va directo a la API. */
    public function oneClickUrl(string $email): string
    {
        return rtrim((string) config('app.url'), '/').'/api/storefront/v1/newsletter/baja/'.$this->unsubscribeToken($email);
    }

    private function markSubscribed(NewsletterSubscriber $subscriber, string $source, ?string $ip): void
    {
        DB::transaction(function () use ($subscriber, $source, $ip) {
            $subscriber->update(['status' => 'subscribed', 'confirmed_at' => now(), 'confirm_token_hash' => null, 'unsubscribed_at' => null, 'unsubscribe_reason' => null]);
            Consent::query()->create(['customer_id' => $subscriber->customer_id, 'email' => $subscriber->email, 'purpose' => 'marketing', 'granted' => true, 'source' => $source, 'ip' => $ip, 'text_version' => 'confirmado']);
            Customer::query()->where('email', $subscriber->email)->update(['marketing_opt_in' => true]);
        });
    }

    private function sign(string $value): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $value, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    private function storefrontUrl(string $path): string
    {
        return rtrim((string) config('flux.storefront_url'), '/').$path;
    }
}
