<?php

namespace App\Domain\Marketing\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Marketing\Mail\MarketingMail;
use App\Domain\Marketing\Models\Automation;
use App\Domain\Marketing\Models\AutomationRun;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Automatizaciones de correo. Requieren consentimiento vigente: solo se escribe a suscritos confirmados,
 * cada persona recibe cada automatización una sola vez por hecho (carrito, pedido o suscripción),
 * y solo cuentan hechos posteriores a la activación (activar no le escribe a toda la base antigua).
 */
class AutomationRunner
{
    public const DEFAULTS = [
        'welcome' => [
            'name' => 'Bienvenida', 'delay_hours' => 0,
            'subject' => 'Bienvenida a {tienda}',
            'heading' => '¡Gracias por suscribirte!',
            'body' => "Desde ahora serás de los primeros en saber de nuestras novedades y ofertas.\n\nMientras tanto, te invitamos a conocer el catálogo.",
            'button_label' => 'Ver el catálogo', 'button_url' => '/catalogo',
        ],
        'abandoned_cart' => [
            'name' => 'Carrito abandonado', 'delay_hours' => 4,
            'subject' => 'Dejaste productos en tu carrito',
            'heading' => '¿Te quedó algo pendiente?',
            'body' => 'Guardamos tu carrito para que termines tu compra cuando quieras. El precio y el stock se revisan al pagar.',
            'button_label' => 'Volver a mi carrito', 'button_url' => '/carrito',
        ],
        'post_purchase' => [
            'name' => 'Después de la compra', 'delay_hours' => 168,
            'subject' => '¿Qué te pareció tu compra?',
            'heading' => 'Gracias por comprar en {tienda}',
            'body' => 'Esperamos que tu pedido haya llegado bien. Si necesitas ayuda con algo, responde este correo.',
            'button_label' => 'Ver novedades', 'button_url' => '/catalogo?sort=newest',
        ],
    ];

    public function __construct(private readonly CampaignService $campaigns) {}

    public static function seedDefaults(): void
    {
        foreach (self::DEFAULTS as $trigger => $values) {
            Automation::query()->firstOrCreate(['trigger' => $trigger], $values);
        }
    }

    /** @return array<string, int> enviados por automatización */
    public function run(): array
    {
        $sent = [];
        foreach (Automation::query()->where('is_active', true)->whereNotNull('active_since')->get() as $automation) {
            $sent[$automation->trigger] = 0;
            foreach ($this->candidates($automation) as [$type, $id, $email, $name, $items]) {
                $sent[$automation->trigger] += (int) $this->send($automation, $type, $id, $email, $name, $items);
            }
        }

        return $sent;
    }

    /** @return Collection<int, array{0: string, 1: int, 2: string, 3: ?string, 4: array}> */
    private function candidates(Automation $a): Collection
    {
        $due = now()->subHours($a->delay_hours);
        $since = $a->active_since;
        $done = fn (string $type) => AutomationRun::query()->where('automation_id', $a->id)->where('subject_type', $type)->select('subject_id');
        $subscribed = NewsletterSubscriber::query()->subscribed()->select('email');

        return match ($a->trigger) {
            'welcome' => NewsletterSubscriber::query()->subscribed()
                ->where('confirmed_at', '<=', $due)->where('confirmed_at', '>=', $since)
                ->whereNotIn('id', $done('subscriber'))->limit(500)->get()
                ->map(fn ($s) => ['subscriber', $s->id, $s->email, $s->name, []]),
            'abandoned_cart' => Cart::query()->where('status', 'active')->whereNotNull('email')
                ->whereHas('items')->where('last_activity_at', '<=', $due)->where('last_activity_at', '>=', $since)
                ->whereIn('email', $subscribed)->whereNotIn('id', $done('cart'))
                ->with(['items.variant.product', 'items.variant.attributeValues', 'customer'])->limit(500)->get()
                ->map(fn (Cart $c) => ['cart', $c->id, $c->email, $c->customer?->first_name, $c->items->map(fn ($i) => [
                    'name' => $i->variant?->product?->name ?? '', 'variant' => $i->variant?->attributeValues->pluck('label')->implode(' / ') ?: null, 'quantity' => $i->quantity,
                ])->all()]),
            'post_purchase' => Order::query()->where('payment_status', 'paid')->where('status', '!=', 'cancelled')
                ->where('paid_at', '<=', $due)->where('paid_at', '>=', $since)
                ->whereIn('email', $subscribed)->whereNotIn('id', $done('order'))->limit(500)->get()
                ->map(fn (Order $o) => ['order', $o->id, $o->email, $o->first_name, []]),
            default => collect(),
        };
    }

    private function send(Automation $a, string $type, int $id, string $email, ?string $name, array $items): bool
    {
        try {
            $run = AutomationRun::query()->create(['automation_id' => $a->id, 'subject_type' => $type, 'subject_id' => $id, 'email' => $email, 'status' => 'sent']);
        } catch (UniqueConstraintViolationException) {
            return false; // otro proceso ya lo envió
        }
        // Se revisa otra vez justo antes de enviar
        if (! NewsletterSubscriber::query()->subscribed()->where('email', $email)->exists()) {
            $run->update(['status' => 'skipped', 'error' => 'Sin suscripción vigente']);

            return false;
        }
        try {
            Mail::to($email)->send(new MarketingMail(
                $email, $a->subject, $a->heading, $a->body, $a->button_label,
                $a->button_url ? $this->campaigns->absolute($a->button_url) : null, null, ['nombre' => (string) $name], $items,
            ));

            return true;
        } catch (\Throwable $e) {
            $run->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }
}
