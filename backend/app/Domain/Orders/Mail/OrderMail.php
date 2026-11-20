<?php

namespace App\Domain\Orders\Mail;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Orders\Services\PaymentSettings;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Base de los correos de pedido: datos de la tienda y enlace de seguimiento. */
abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
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

    protected function storeName(): string
    {
        return app(SettingsRepository::class)->get('business', 'store_name', config('app.name'));
    }
}
