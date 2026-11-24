<?php

namespace App\Domain\Marketing\Mail;

use App\Domain\Marketing\Services\NewsletterService;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * Correo de marketing (campañas y automatizaciones). Siempre lleva el enlace de baja visible y las
 * cabeceras List-Unsubscribe y List-Unsubscribe-Post (baja en un clic, RFC 8058), que Gmail y Yahoo exigen.
 * El texto es Markdown escrito en el panel: el HTML escrito a mano se muestra como texto.
 * Variables: {nombre} y {tienda}.
 */
class MarketingMail extends Mailable
{
    public function __construct(
        protected string $email,
        protected string $subjectLine,
        protected string $headingText,
        protected string $bodyText,
        protected ?string $buttonText = null,
        protected ?string $buttonLink = null,
        protected ?string $preheaderText = null,
        protected array $vars = [],
        protected array $lineItems = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: EmailTemplates::fill($this->subjectLine, $this->variables(), markdown: false));
    }

    public function headers(): Headers
    {
        $news = app(NewsletterService::class);

        return new Headers(text: [
            'List-Unsubscribe' => '<'.$news->oneClickUrl($this->email).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        $vars = $this->variables();

        return new Content(markdown: 'mail.marketing.message', with: [
            'heading' => EmailTemplates::fill($this->headingText, $vars, markdown: true),
            'html' => Str::markdown(EmailTemplates::fill($this->bodyText, $vars, markdown: true), ['html_input' => 'escape', 'allow_unsafe_links' => false]),
            'buttonLabel' => $this->buttonText,
            'buttonUrl' => $this->buttonLink,
            'preheader' => $this->preheaderText,
            'items' => $this->lineItems,
            'unsubscribeUrl' => app(NewsletterService::class)->unsubscribeUrl($this->email),
            'store' => $vars['tienda'],
        ]);
    }

    private function variables(): array
    {
        return ['tienda' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name'))] + $this->vars + ['nombre' => ''];
    }
}
