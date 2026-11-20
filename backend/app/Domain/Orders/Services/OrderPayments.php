<?php

namespace App\Domain\Orders\Services;

use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Mail\NewOrderNotificationMail;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Domain\System\Services\AuditLogger;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Confirmación manual de la transferencia, anulación y vencimiento de pedidos impagos.
 * La confirmación es idempotente: confirmar dos veces no descuenta el stock dos veces.
 */
class OrderPayments
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Confirmación manual (transferencia). Devuelve false si el pago ya estaba confirmado. */
    public function confirm(Order $order, int $amount, ?string $reference = null): bool
    {
        $payment = Payment::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
        if ($payment->status !== 'paid' && $amount !== $payment->amount) {
            throw ValidationException::withMessages(['amount' => 'El monto recibido ($'.number_format($amount, 0, ',', '.').') no coincide con el total del pedido ($'.number_format($payment->amount, 0, ',', '.').'). Revisa la transferencia.']);
        }

        return $this->markPaid($order, $payment, ['reference' => $reference], manual: true);
    }

    /**
     * Marca el pago como pagado y el pedido en preparación, descontando el stock reservado.
     * Idempotente: si el pago ya estaba pagado no hace nada y devuelve false.
     * Lo usan la confirmación manual y las pasarelas (después de confirmar con la pasarela).
     *
     * @param  array{reference?: ?string, authorization_code?: ?string, card_last4?: ?string, payment_type?: ?string, installments?: ?int}  $details
     */
    public function markPaid(Order $order, Payment $payment, array $details = [], bool $manual = false): bool
    {
        $needsRefund = false;
        $confirmed = DB::transaction(function () use ($order, $payment, $details, $manual, &$needsRefund) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                return false;
            }
            $expired = $order->status === 'cancelled' && $order->payment_status === 'expired';
            if ($manual && $order->status === 'cancelled' && ! $expired) {
                throw ValidationException::withMessages(['amount' => 'El pedido está cancelado. No se puede confirmar su pago.']);
            }

            $payment->update([
                'status' => 'paid', 'confirmed_at' => now(), 'confirmed_by' => $manual ? Auth::id() : null,
                ...array_intersect_key($details, array_flip(['reference', 'authorization_code', 'card_last4', 'payment_type', 'installments'])),
            ]);
            $from = $order->status;

            // Un pago que llega por la pasarela después de que el pedido se anuló: si ya no hay stock, hay que devolver el dinero
            if ($order->status === 'cancelled') {
                try {
                    DB::transaction(fn () => $this->consumeStock($order));
                } catch (ValidationException) {
                    $needsRefund = true;
                    $order->update(['payment_status' => 'paid', 'paid_total' => $payment->amount, 'paid_at' => now()]);
                    OrderEvents::record($order, 'note', ['text' => 'Llegó el pago de un pedido anulado y ya no hay stock para reponerlo. Hay que reembolsar al cliente.'], visible: false, actor: 'system');
                    app(AuditLogger::class)->record('Order.payment_confirmed', $order, ['payment_status' => $order->getOriginal('payment_status')], ['payment_status' => 'paid', 'requires_refund' => true]);

                    return true;
                }
            } else {
                $this->consumeStock($order);
            }

            $order->update(['status' => 'processing', 'payment_status' => 'paid', 'paid_total' => $payment->amount, 'paid_at' => now(), 'cancelled_at' => null]);
            OrderEvents::record($order, 'payment_confirmed', array_filter([
                'amount' => $payment->amount, 'method' => $payment->method_code,
                'reference' => $details['reference'] ?? null, 'authorization_code' => $details['authorization_code'] ?? null,
            ]), visible: true, from: $from, to: 'processing', actor: $manual ? 'user' : 'provider');
            app(AuditLogger::class)->record('Order.payment_confirmed', $order, ['payment_status' => 'pending'], ['payment_status' => 'paid', 'amount' => $payment->amount, 'method' => $payment->method_code]);

            return true;
        });

        if ($confirmed && ! $needsRefund) {
            $order = $order->fresh();
            Mail::to($order->email)->queue(new PaymentConfirmedMail($order));
            // Con pasarela, la tienda se entera del pedido recién cuando está pagado
            if ($payment->method_code !== 'bank_transfer' && ($notify = app(SettingsRepository::class)->get('business', 'contact_email'))) {
                Mail::to($notify)->queue(new NewOrderNotificationMail($order));
            }
        }

        return $confirmed;
    }

    public function cancel(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->isCancellable()) {
                throw ValidationException::withMessages(['reason' => 'Solo se cancelan pedidos pendientes de pago. Un pedido pagado se anula con un reembolso.']);
            }
            $this->releaseStock($order, 'released', $reason);
            Payment::query()->where('order_id', $order->id)->whereIn('status', ['pending', 'failed', 'aborted'])->update(['status' => 'cancelled']);
            $order->update(['status' => 'cancelled', 'payment_status' => 'cancelled', 'cancelled_at' => now()]);
            OrderEvents::record($order, 'cancelled', ['reason' => $reason], visible: true, from: 'pending', to: 'cancelled');
            app(AuditLogger::class)->record('Order.cancelled', $order, ['status' => 'pending'], ['status' => 'cancelled', 'reason' => $reason]);
        });
    }

    /**
     * Vence los pedidos cuyo plazo de pago pasó y libera el stock. Lo ejecuta el scheduler cada minuto.
     * Considera el último intento de pago del pedido: pendiente, rechazado o no completado.
     * Un pago con pasarela que sigue "pendiente" se consulta antes (PaymentProcessor::reconcileDue); si la
     * pasarela no responde se espera hasta 2 horas antes de anular, para no anular un pago que sí se hizo.
     */
    public function expireOverdue(int $limit = 200): int
    {
        $count = 0;
        Payment::query()->whereIn('status', ['pending', 'failed', 'aborted'])->where('expires_at', '<=', now())
            ->whereHas('order', fn ($q) => $q->where('status', 'pending'))
            ->orderBy('expires_at')->limit($limit)->get()
            ->each(function (Payment $payment) use (&$count) {
                if ($payment->status === 'pending' && $payment->external_id && $payment->expires_at->gt(now()->subHours(2))) {
                    return;
                }
                DB::transaction(function () use ($payment, &$count) {
                    $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                    $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                    $latest = Payment::query()->where('order_id', $order->id)->max('id');
                    if ($order->status !== 'pending' || $payment->id !== $latest || ! in_array($payment->status, ['pending', 'failed', 'aborted'], true)) {
                        return;
                    }
                    $this->releaseStock($order, 'expired', 'Plazo de pago vencido');
                    if ($payment->status === 'pending') {
                        $payment->update(['status' => 'expired']);
                    }
                    $order->update(['status' => 'cancelled', 'payment_status' => 'expired', 'cancelled_at' => now()]);
                    OrderEvents::record($order, 'payment_expired', [], visible: true, from: 'pending', to: 'cancelled', actor: 'system');
                    $count++;
                });
            });

        return $count;
    }

    /** Convierte las reservas del pedido en "Salida por venta". Si vencieron, intenta reservar de nuevo. */
    private function consumeStock(Order $order): void
    {
        $reservations = StockReservation::query()->where('reference_type', 'order')->where('reference_id', $order->uuid)->get();
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if (! $item->variant_id) {
                continue;
            }
            $active = $reservations->first(fn ($r) => $r->variant_id === $item->variant_id && in_array($r->status, ['active', 'consumed'], true));
            if (! $active) {
                $active = $this->inventory->reserve($item->variant_id, $item->quantity, 'order', $order->uuid, 60);
                if ($active === null) {
                    continue; // no controla stock
                }
            }
            $this->inventory->consume($active, $order->number);
        }
    }

    private function releaseStock(Order $order, string $status, string $reason): void
    {
        StockReservation::query()->where('reference_type', 'order')->where('reference_id', $order->uuid)->where('status', 'active')->get()
            ->each(fn (StockReservation $r) => $this->inventory->release($r, $status, $reason));
    }
}
