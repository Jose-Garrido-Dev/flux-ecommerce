<?php

namespace App\Domain\Customers\Mail;

use App\Domain\Customers\Models\Customer;
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
    use Queueable, SerializesModels;

    public function __construct(public Customer $customer, public string $kind, public string $url)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $store = app(SettingsRepository::class)->get('business', 'store_name', config('app.name'));

        return new Envelope(subject: match ($this->kind) {
            'verify' => "Confirma tu correo · $store",
            default => "Restablece tu contraseña · $store",
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.customers.'.$this->kind, with: [
            'name' => $this->customer->first_name,
            'url' => $this->url,
            'store' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name')),
            'minutes' => config('auth.passwords.customers.expire', 60),
        ]);
    }
}
