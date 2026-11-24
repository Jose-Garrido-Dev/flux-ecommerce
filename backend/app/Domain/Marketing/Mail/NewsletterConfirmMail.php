<?php

namespace App\Domain\Marketing\Mail;

use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Services\NewsletterService;
use App\Domain\Notifications\Concerns\TracksDelivery;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Doble opt-in: el enlace para confirmar la suscripción al newsletter. */
class NewsletterConfirmMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksDelivery;

    public function __construct(public NewsletterSubscriber $subscriber, public string $url)
    {
        $this->afterCommit();
    }

    public function templateKey(): string
    {
        return 'newsletter_confirm';
    }

    private function template(): array
    {
        return EmailTemplates::render('newsletter_confirm', ['nombre' => (string) $this->subscriber->name]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->template()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.marketing.confirm', with: [
            'tpl' => $this->template(),
            'url' => $this->url,
            'days' => NewsletterService::CONFIRM_DAYS,
            'store' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name')),
        ]);
    }
}
