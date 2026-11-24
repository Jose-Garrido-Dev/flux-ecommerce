<?php

namespace App\Domain\Orders\Mail;

use App\Domain\Notifications\Concerns\TracksDelivery;
use App\Domain\Notifications\Services\EmailTemplates;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Orders\Services\PaymentSettings;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Base de los correos de pedido: plantilla editable (asunto, título, texto), datos de la tienda,
 * enlace de seguimiento e historial de envío.
 */
abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, TracksDelivery;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    /** Vista Markdown del correo (mail.*). */
    abstract protected function markdownView(): string;

    /** Variables propias de la plantilla, además de nombre, pedido y total. */
    protected function variables(): array
    {
        return [];
    }

    /** Datos propios de la vista. */
    protected function viewData(): array
    {
        return [];
    }

    protected function logContext(): array
    {
        return ['order_id' => $this->order->id, 'customer_id' => $this->order->customer_id];
    }

    protected function template(): array
    {
        return EmailTemplates::render($this->templateKey(), [
            'nombre' => $this->order->first_name,
            'pedido' => $this->order->number,
            'total' => '$'.number_format($this->order->grand_total, 0, ',', '.'),
            ...$this->variables(),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->template()['subject']);
    }

    public function content(): Content
    {
        return new Content(markdown: $this->markdownView(), with: [...$this->common(), 'tpl' => $this->template(), ...$this->viewData()]);
    }

    protected function common(): array
    {
        $this->order->loadMissing(['items', 'shippingLine']);
        $business = app(SettingsRepository::class)->group('business');

        return [
            'order' => $this->order,
            'store' => $business['store_name'] ?? config('app.name'),
            'contact' => $business['contact_email'] ?? null,
            'trackingUrl' => rtrim((string) config('flux.storefront_url'), '/').'/pedido/'.OrderTokens::for($this->order->uuid),
            'transfer' => app(PaymentSettings::class)->transferInstructions(),
        ];
    }

    protected static function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }
}
