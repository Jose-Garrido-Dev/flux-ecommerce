<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de estado del pedido. Todo cambio de estado pasa por aquí; una transición prohibida
 * lanza ValidationException (HTTP 422) con el motivo en español.
 *
 * pendiente → en preparación (pago confirmado) | cancelado
 * en preparación → completado (todo entregado o retirado) | cancelado (solo con reembolso total y sin despachar)
 * cancelado → en preparación (solo un pedido vencido que recibe un pago tardío)
 * completado → nada: lo que vuelve después se gestiona como devolución
 */
final class OrderStateMachine
{
    public const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => ['processing'],
    ];

    /** Motivo por el que no se puede, o null si se puede. $after son los campos del pedido tal como quedarían. */
    public static function reason(Order $order, string $to, array $after = []): ?string
    {
        $from = $order->status;
        $label = fn (string $s) => Order::STATUSES[$s] ?? $s;
        if (! array_key_exists($to, Order::STATUSES)) {
            return 'Estado de pedido no válido.';
        }
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            return match (true) {
                $from === 'completed' => 'El pedido ya está completado: si el cliente devuelve algo, registra una devolución.',
                $from === $to => "El pedido ya está «{$label($to)}».",
                default => "Un pedido «{$label($from)}» no puede pasar a «{$label($to)}».",
            };
        }
        $payment = $after['payment_status'] ?? $order->payment_status;
        $fulfillment = $after['fulfillment_status'] ?? $order->fulfillment_status;

        return match ($to) {
            'processing' => match (true) {
                $payment !== 'paid' => 'Un pedido pasa a preparación solo con el pago confirmado.',
                $from === 'cancelled' && $order->payment_status !== 'expired' => 'Solo un pedido anulado por plazo vencido se reactiva con un pago.',
                default => null,
            },
            'cancelled' => match (true) {
                $from === 'pending' && $order->payment_status === 'paid' => 'El pedido ya está pagado: se anula con un reembolso total.',
                $from === 'processing' && $fulfillment !== 'unfulfilled' => 'El pedido ya tiene despachos: no se cancela, registra una devolución.',
                $from === 'processing' && $payment !== 'refunded' => 'Un pedido pagado se cancela con un reembolso total.',
                default => null,
            },
            'completed' => in_array($fulfillment, ['delivered', 'picked_up'], true)
                ? null : 'El pedido se completa cuando todo está entregado o retirado.',
            default => null,
        };
    }

    public static function can(Order $order, string $to, array $after = []): bool
    {
        return self::reason($order, $to, $after) === null;
    }

    /** @throws ValidationException */
    public static function assert(Order $order, string $to, array $after = [], string $field = 'status'): void
    {
        if ($reason = self::reason($order, $to, $after)) {
            throw ValidationException::withMessages([$field => $reason]);
        }
    }
}
