<?php

namespace App\Domain\Marketing\Services;

use App\Domain\Content\Services\BlockSchema;
use App\Domain\Marketing\Jobs\SendCampaignBatch;
use App\Domain\Marketing\Mail\MarketingMail;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignRecipient;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\System\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Campañas al newsletter. Solo reciben los suscritos confirmados; la suscripción se revisa otra vez
 * justo antes de enviar a cada persona, así una baja durante el envío se respeta.
 * Se envía en lotes con pausa entre ellos para no superar los límites del servidor de correo.
 */
class CampaignService
{
    public const BATCH = 50;

    /** Segundos entre lotes. */
    public const BATCH_DELAY = 30;

    public function validate(array $data): void
    {
        if (filled($data['button_url'] ?? null) && ! BlockSchema::validLink((string) $data['button_url'])) {
            throw ValidationException::withMessages(['button_url' => 'El enlace del botón debe ser una ruta de la tienda (/catalogo) o una dirección https://.']);
        }
        if (filled($data['button_url'] ?? null) xor filled($data['button_label'] ?? null)) {
            throw ValidationException::withMessages(['button_label' => 'Completa el texto y el enlace del botón, o deja ambos vacíos.']);
        }
    }

    /** Programa el envío (sin fecha = ahora). */
    public function schedule(Campaign $campaign, ?\DateTimeInterface $at = null): void
    {
        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Esta campaña ya se envió o se canceló.']);
        }
        $this->validate($campaign->toArray());
        if ($this->audienceQuery($campaign->audience)->doesntExist()) {
            throw ValidationException::withMessages(['audience' => 'No hay suscritos confirmados para esta audiencia.']);
        }
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at ?? now()]);
        app(AuditLogger::class)->record('Campaign.scheduled', $campaign, [], ['scheduled_at' => $campaign->scheduled_at?->toIso8601String()]);
        if (! $at || $at <= now()) {
            $this->start($campaign);
        }
    }

    public function cancel(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['scheduled', 'sending'], true)) {
            throw ValidationException::withMessages(['status' => 'Solo se puede cancelar una campaña programada o en envío.']);
        }
        $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);
        $campaign->recipients()->where('status', 'pending')->update(['status' => 'skipped', 'error' => 'Campaña cancelada']);
        $this->refreshCounts($campaign);
        app(AuditLogger::class)->record('Campaign.cancelled', $campaign);
    }

    /** Arma la lista de destinatarios con los suscritos de ese momento y pone los lotes en cola. */
    public function start(Campaign $campaign): void
    {
        $started = DB::transaction(function () use ($campaign) {
            $campaign = Campaign::query()->lockForUpdate()->find($campaign->id);
            if ($campaign->status !== 'scheduled') {
                return null;
            }
            $now = now();
            $this->audienceQuery($campaign->audience)->select(['id', 'email'])->orderBy('id')
                ->chunk(1000, function ($subs) use ($campaign) {
                    CampaignRecipient::query()->insertOrIgnore($subs->map(fn ($s) => [
                        'campaign_id' => $campaign->id, 'subscriber_id' => $s->id, 'email' => $s->email, 'status' => 'pending',
                    ])->all());
                });
            $campaign->update(['status' => 'sending', 'started_at' => $now, 'recipients_count' => $campaign->recipients()->count()]);

            return $campaign;
        });
        if (! $started) {
            return;
        }
        $n = 0;
        $started->recipients()->where('status', 'pending')->select('id')->orderBy('id')->chunk(self::BATCH, function ($rows) use ($started, &$n) {
            SendCampaignBatch::dispatch($started->id, $rows->pluck('id')->all())->delay(now()->addSeconds($n++ * self::BATCH_DELAY));
        });
        if ($n === 0) {
            $started->update(['status' => 'sent', 'finished_at' => now()]);
        }
    }

    /** Envía a un destinatario si sigue suscrito. */
    public function deliver(Campaign $campaign, CampaignRecipient $recipient): void
    {
        $subscriber = $recipient->subscriber_id ? NewsletterSubscriber::query()->find($recipient->subscriber_id) : null;
        if (! $subscriber || $subscriber->status !== 'subscribed') {
            $recipient->update(['status' => 'skipped', 'error' => 'Se dio de baja antes del envío']);

            return;
        }
        $recipient->increment('attempts');
        try {
            Mail::to($recipient->email)->send($this->mail($campaign, $recipient->email, $subscriber->name, $this->clickUrl($recipient)));
            $recipient->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
        } catch (\Throwable $e) {
            $recipient->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }

    /** Prueba al correo del usuario del panel. No cuenta como envío de la campaña. */
    public function sendTest(Campaign $campaign, string $email): void
    {
        $this->validate($campaign->toArray());
        Mail::to($email)->send($this->mail($campaign, $email, null, $campaign->button_url ? $this->absolute($campaign->button_url) : null));
    }

    public function finishIfDone(Campaign $campaign): void
    {
        $this->refreshCounts($campaign);
        if ($campaign->status === 'sending' && $campaign->recipients()->where('status', 'pending')->doesntExist()) {
            $campaign->update(['status' => 'sent', 'finished_at' => now()]);
        }
    }

    public function retryFailed(Campaign $campaign): int
    {
        $ids = $campaign->recipients()->where('status', 'failed')->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }
        CampaignRecipient::query()->whereIn('id', $ids)->update(['status' => 'pending', 'error' => null]);
        $campaign->update(['status' => 'sending', 'finished_at' => null]);
        foreach (array_chunk($ids, self::BATCH) as $i => $chunk) {
            SendCampaignBatch::dispatch($campaign->id, $chunk)->delay(now()->addSeconds($i * self::BATCH_DELAY));
        }

        return count($ids);
    }

    public function refreshCounts(Campaign $campaign): void
    {
        $counts = $campaign->recipients()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $campaign->update([
            'sent_count' => (int) ($counts['sent'] ?? 0), 'failed_count' => (int) ($counts['failed'] ?? 0), 'skipped_count' => (int) ($counts['skipped'] ?? 0),
            'clicks_count' => $campaign->recipients()->whereNotNull('clicked_at')->count(),
        ]);
    }

    public function audienceQuery(string $audience)
    {
        $paid = fn ($q) => $q->selectRaw('1')->from('orders')->whereColumn('orders.email', 'newsletter_subscribers.email')->where('orders.payment_status', 'paid');

        return NewsletterSubscriber::query()->subscribed()
            ->when($audience === 'buyers', fn ($q) => $q->whereExists($paid))
            ->when($audience === 'non_buyers', fn ($q) => $q->whereNotExists($paid));
    }

    /** Clic en el botón: se registra y se redirige al enlace guardado en la campaña (nunca a uno que venga en la URL). */
    public function clickUrl(CampaignRecipient $recipient): ?string
    {
        if (! $recipient->campaign?->button_url) {
            return null;
        }
        $payload = $recipient->id;

        return rtrim((string) config('app.url'), '/')."/api/storefront/v1/newsletter/clic/$payload.".$this->sign("clic|$payload");
    }

    public function registerClick(string $token): ?string
    {
        [$id, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if (! ctype_digit($id) || ! hash_equals($this->sign("clic|$id"), $sig)) {
            return null;
        }
        $recipient = CampaignRecipient::query()->with('campaign')->find((int) $id);
        if (! $recipient?->campaign?->button_url) {
            return null;
        }
        if (! $recipient->clicked_at) {
            $recipient->update(['clicked_at' => now()]);
            $recipient->campaign->increment('clicks_count');
        }

        return $this->absolute($recipient->campaign->button_url);
    }

    public function absolute(string $url): string
    {
        return str_starts_with($url, '/') ? rtrim((string) config('flux.storefront_url'), '/').$url : $url;
    }

    private function mail(Campaign $campaign, string $email, ?string $name, ?string $buttonUrl): MarketingMail
    {
        return new MarketingMail($email, $campaign->subject, $campaign->heading, $campaign->body, $campaign->button_label, $buttonUrl, $campaign->preheader, ['nombre' => (string) $name]);
    }

    private function sign(string $value): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $value, (string) config('app.key'), true)), '+/', '-_'), '=');
    }
}
