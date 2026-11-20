<?php

namespace App\Domain\Orders\Services;

use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Mail\PaymentConfirmedMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Domain\System\Services\AuditLogger;
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

    /** Devuelve false si el pago ya estaba confirmado. */
    public function confirm(Order $order, int $amount, ?string $reference = null): bool
    {
        $confirmed = DB::transaction(function () use ($order, $amount, $reference) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('order_id', $order->id)->latest('id')->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                return false;
            }
            if ($order->status === 'cancelled' && $payment->status !== 'expired') {
                throw ValidationException::withMessages(['amount' => 'El pedido está cancelado. No se puede confirmar su pago.']);
            }
            if ($amount !== $payment->amount) {
                throw ValidationException::withMessages(['amount' => 'El monto recibido ($'.number_format($amount, 0, ',', '.').') no coincide con el total del pedido ($'.number_format($payment->amount, 0, ',', '.').'). Revisa la transferencia.']);
            }

            $this->consumeStock($order);

            $payment->update(['status' => 'paid', 'reference' => $reference, 'confirmed_at' => now(), 'confirmed_by' => Auth::id()]);
            $from = $order->status;
            $order->update(['status' => 'processing', 'payment_status' => 'paid', 'paid_total' => $amount, 'paid_at' => now(), 'cancelled_at' => null]);
            OrderEvents::record($order, 'payment_confirmed', array_filter(['amount' => $amount, 'reference' => $reference]), visible: true, from: $from, to: 'processing');
            app(AuditLogger::class)->record('Order.payment_confirmed', $order, ['payment_status' => 'pending'], ['payment_status' => 'paid', 'amount' => $amount]);

            return true;
        });

        if ($confirmed) {
            Mail::to($order->email)->queue(new PaymentConfirmedMail($order->fresh()));
        }

        return $confirmed;
    }

    public function cancel(Order $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->isCancellable()) {
                throw ValidationException::withMessages(['reason' => 'Solo se cancelan pedidos pendientes de pago. Un pedido pagado requiere un reembolso (Fase 6).']);
            }
            $this->releaseStock($order, 'released', $reason);
            Payment::query()->where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            $order->update(['status' => 'cancelled', 'payment_status' => 'cancelled', 'cancelled_at' => now()]);
            OrderEvents::record($order, 'cancelled', ['reason' => $reason], visible: true, from: 'pending', to: 'cancelled');
            app(AuditLogger::class)->record('Order.cancelled', $order, ['status' => 'pending'], ['status' => 'cancelled', 'reason' => $reason]);
        });
    }

    /** Vence los pedidos cuyo plazo de pago pasó: libera el stock. Lo ejecuta el scheduler cada minuto. */
    public function expireOverdue(int $limit = 200): int
    {
        $count = 0;
        Payment::query()->where('status', 'pending')->where('expires_at', '<=', now())->orderBy('expires_at')->limit($limit)->get()
            ->each(function (Payment $payment) use (&$count) {
                DB::transaction(function () use ($payment, &$count) {
                    $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();
                    if ($payment->status !== 'pending') {
                        return;
                    }
                    $order = Order::query()->whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
                    $this->releaseStock($order, 'expired', 'Plazo de pago vencido');
                    $payment->update(['status' => 'expired']);
                    if ($order->status === 'pending') {
                        $order->update(['status' => 'cancelled', 'payment_status' => 'expired', 'cancelled_at' => now()]);
                        OrderEvents::record($order, 'payment_expired', [], visible: true, from: 'pending', to: 'cancelled', actor: 'system');
                    }
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
