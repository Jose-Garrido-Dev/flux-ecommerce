<?php

namespace App\Domain\Payments\Services;

use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Domain\Orders\Services\CheckoutException;
use App\Domain\Orders\Services\OrderEvents;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Data\PaymentInitiation;
use App\Domain\System\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Pagos con pasarela: iniciar (o reintentar), procesar el regreso del cliente y conciliar.
 * El regreso del navegador nunca marca un pago como pagado: siempre se confirma con la pasarela
 * y se compara monto y orden de compra. Procesar el mismo regreso dos veces no tiene efecto doble.
 */
class PaymentProcessor
{
    /** Minutos que el cliente tiene para pagar con pasarela (el stock queda reservado ese tiempo). */
    public const GATEWAY_MINUTES = 30;

    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly OrderPayments $orders,
    ) {}

    /**
     * Inicia el pago de un pedido pendiente. Si el último intento ya se usó, crea uno nuevo.
     * Devuelve null si el pedido ya quedó pagado (p. ej. el cliente pagó en otra pestaña).
     *
     * @throws CheckoutException
     */
    public function start(Order $order): ?PaymentInitiation
    {
        $latest = $order->payments()->latest('id')->first();
        if (! $latest || $order->status !== 'pending') {
            throw new CheckoutException('order_not_payable', 'Este pedido ya no se puede pagar.', 409);
        }
        if (! $this->registry->has($latest->method_code)) {
            throw new CheckoutException('order_not_payable', 'Este pedido se paga por transferencia bancaria.', 409);
        }
        if (! $this->registry->isAvailable($latest->method_code)) {
            throw new CheckoutException('payment_unavailable', 'El medio de pago no está disponible en este momento.', 422);
        }

        // Un intento anterior que quedó abierto puede haberse pagado: se confirma antes de crear otro
        if ($latest->status === 'pending' && $latest->external_id) {
            if ($this->resolve($latest) === 'paid') {
                return null;
            }
            $latest->refresh();
        }

        $payment = DB::transaction(function () use ($order) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending') {
                throw new CheckoutException('order_not_payable', 'Este pedido ya no se puede pagar.', 409);
            }
            $latest = Payment::query()->where('order_id', $order->id)->latest('id')->lockForUpdate()->firstOrFail();
            if ($latest->status === 'paid') {
                return null;
            }
            $expires = now()->addMinutes(self::GATEWAY_MINUTES);

            if ($latest->status === 'pending' && ! $latest->external_id) {
                $payment = $latest; // primer intento, recién creado en el checkout
                $payment->update(['expires_at' => $expires]);
            } else {
                if ($latest->status === 'pending') {
                    $latest->update(['status' => 'aborted', 'failure_reason' => 'Reemplazado por un nuevo intento de pago.']);
                }
                $attempt = $latest->attempt + 1;
                if ($attempt > 20) {
                    throw new CheckoutException('too_many_attempts', 'Se alcanzó el máximo de intentos de pago para este pedido. Escríbenos para ayudarte.', 429);
                }
                $payment = $order->payments()->create([
                    'method_code' => $latest->method_code, 'attempt' => $attempt, 'amount' => $order->grand_total,
                    'gateway_order' => $order->number.'-'.$attempt, 'expires_at' => $expires,
                ]);
            }

            // El stock sigue apartado mientras dure este intento
            StockReservation::query()->where('reference_type', 'order')->where('reference_id', $order->uuid)
                ->where('status', 'active')->where('expires_at', '<', $expires)->update(['expires_at' => $expires]);

            return $payment;
        });

        if ($payment === null) {
            return null;
        }

        $initiation = $this->registry->driver($payment->method_code)
            ->create($payment, route('payments.return', ['gateway' => $payment->method_code]));
        $payment->update(['external_id' => $initiation->token]);

        return $initiation;
    }

    /**
     * Regreso del cliente desde la pasarela.
     *
     * @return array{0: ?Order, 1: string} pedido y resultado: paid, rejected, aborted, pending o unknown
     */
    public function handleReturn(string $gateway, array $params): array
    {
        $driver = $this->registry->driver($gateway);
        $ref = $driver->parseReturn($params);

        $payment = null;
        if ($ref->token) {
            $payment = Payment::query()->where('method_code', $gateway)->where('external_id', $ref->token)->first();
        }
        if (! $payment && $ref->gatewayOrder) {
            $payment = Payment::query()->where('method_code', $gateway)->where('gateway_order', $ref->gatewayOrder)->first();
        }
        TransactionLog::record($payment?->id, $gateway, 'return', $payment ? 'ok' : 'error', $ref->token ?? $ref->gatewayOrder, null,
            ['parametros' => array_keys(array_filter($params, 'is_string')), 'anulado' => $ref->aborted, 'tiempo_agotado' => $ref->timedOut]);

        if (! $payment) {
            return [null, 'unknown'];
        }
        $order = $payment->order;

        if ($ref->aborted || $ref->timedOut) {
            $reason = $ref->aborted ? 'El cliente anuló el pago en Webpay.' : 'Se acabó el tiempo para completar el pago en Webpay.';

            return [$order, $this->apply($payment, new GatewayResult('aborted', message: $reason))];
        }

        return [$order, $this->resolve($payment)];
    }

    /** Confirma con la pasarela un pago pendiente y aplica el resultado. */
    public function resolve(Payment $payment): string
    {
        $payment->refresh();
        if ($payment->status !== 'pending') {
            return $this->outcomeOf($payment);
        }

        return $this->apply($payment, $this->registry->driver($payment->method_code)->confirm($payment));
    }

    /** Aplica el resultado de la pasarela al pago y al pedido. Idempotente. */
    public function apply(Payment $payment, GatewayResult $result): string
    {
        if ($result->approved()) {
            if ($result->amount !== $payment->amount || ($result->gatewayOrder !== null && $result->gatewayOrder !== $payment->gateway_order)) {
                return $this->fail($payment, 'failed', 'La pasarela informó un monto u orden de compra distintos a los del pedido. No se marcó como pagado.', alert: true);
            }
            $this->orders->markPaid($payment->order, $payment, [
                'authorization_code' => $result->authorizationCode, 'card_last4' => $result->cardLast4,
                'payment_type' => $result->paymentType, 'installments' => $result->installments,
            ]);

            return 'paid';
        }

        return match ($result->status) {
            'rejected' => $this->fail($payment, 'failed', $result->message ?? 'El banco rechazó el pago.'),
            'aborted' => $this->fail($payment, 'aborted', $result->message ?? 'El pago no se completó.'),
            default => 'pending',
        };
    }

    /**
     * Conciliación: confirma con la pasarela los pagos que siguen pendientes (el cliente cerró el navegador,
     * se cortó la conexión al volver, etc.). Por defecto, los iniciados hace más de 10 minutos.
     *
     * @return array<string, int> cantidad por resultado
     */
    public function reconcile(int $olderThanMinutes = 10, int $limit = 100): array
    {
        $counts = [];
        Payment::query()->where('status', 'pending')->whereNotNull('external_id')
            ->whereIn('method_code', array_keys(GatewayRegistry::DRIVERS))
            ->where('updated_at', '<=', now()->subMinutes($olderThanMinutes))
            ->where('created_at', '>=', now()->subDays(7)) // Webpay entrega el estado hasta 7 días después
            ->orderBy('id')->limit($limit)->get()
            ->each(function (Payment $payment) use (&$counts) {
                try {
                    $outcome = $this->resolve($payment);
                } catch (GatewayException) {
                    $outcome = 'error';
                }
                // Sin resultado y con el plazo vencido: el cliente no terminó de pagar
                if ($outcome === 'pending' && $payment->fresh()->expires_at?->isPast()) {
                    $outcome = $this->apply($payment, new GatewayResult('aborted', message: 'El cliente no terminó el pago.'));
                }
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            });

        return $counts;
    }

    private function fail(Payment $payment, string $status, string $reason, bool $alert = false): string
    {
        return DB::transaction(function () use ($payment, $status, $reason, $alert) {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending') {
                return $this->outcomeOf($payment);
            }
            $payment->update(['status' => $status, 'failure_reason' => mb_substr($reason, 0, 200)]);
            $order = $payment->order;
            OrderEvents::record($order, $status === 'aborted' ? 'payment_aborted' : 'payment_failed', ['reason' => $reason, 'attempt' => $payment->attempt], visible: true, actor: 'provider');
            if ($alert) {
                app(AuditLogger::class)->record('Order.payment_failed', $order, [], ['reason' => $reason, 'payment' => $payment->uuid]);
            }

            return $status === 'aborted' ? 'aborted' : 'rejected';
        });
    }

    private function outcomeOf(Payment $payment): string
    {
        return match ($payment->status) {
            'paid' => 'paid',
            'failed' => 'rejected',
            'aborted', 'cancelled', 'expired' => 'aborted',
            default => 'pending',
        };
    }
}
