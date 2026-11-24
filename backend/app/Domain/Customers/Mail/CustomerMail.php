<?php

namespace App\Domain\Customers\Mail;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Concerns\TracksDelivery;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Correos de cuenta: verificación y recuperación de contraseña. El enlace lleva a la tienda, no a la API. */
class CustomerMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksDelivery;

    public function __construct(public Customer $customer, public string $kind, public string $url)
    {
        $this->afterCommit();
    }

    public function templateKey(): string
    {
        return $this->kind === 'verify' ? 'customer_verify' : 'customer_reset';
    }

    protected function logContext(): array
    {
        return ['customer_id' => $this->customer->id];
    }

    private function template(): array
    {
        return EmailTemplates::render($this->templateKey(), ['nombre' => $this->customer->first_name]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->template()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customers.'.($this->kind === 'verify' ? 'verify' : 'reset'), with: [
            'tpl' => $this->template(),
            'url' => $this->url,
            'store' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name')),
            'minutes' => config('auth.passwords.customers.expire', 60),
        ]);
    }
}
