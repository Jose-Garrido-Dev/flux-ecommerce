<?php

namespace App\Domain\Payments\Services;

use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Mail\RefundMail;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Domain\Orders\Services\OrderEvents;
use App\Domain\Orders\Services\OrderStateMachine;
use App\Domain\Payments\Models\Refund;
use App\Domain\System\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Reembolsos totales o parciales.
 * - Con pasarela: se pide a la pasarela y solo se registra si la pasarela lo aprueba.
 * - Transferencia: la tienda devuelve el dinero por su banco y aquí lo deja registrado.
 * Un reembolso total de un pedido sin despachar lo anula y, si se pide, devuelve los productos al stock.
 */
class RefundService
{
    public function __construct(
        private readonly GatewayRegistry $registry,
        private readonly InventoryService $inventory,
    ) {}

    public function refund(Order $order, int $amount, string $reason, bool $restock = false, ?int $returnId = null): Refund
    {
        $lock = Cache::lock('flux:refund:order:'.$order->id, 60);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['amount' => 'Ya hay un reembolso en curso para este pedido. Espera unos segundos.']);
        }

        try {
            $payment = Payment::query()->where('order_id', $order->id)->where('status', 'paid')->latest('id')->first();
            if (! $payment) {
                throw ValidationException::withMessages(['amount' => 'Este pedido no tiene un pago confirmado para reembolsar.']);
            }
            $available = $payment->refundable();
            if ($amount < 1 || $amount > $available) {
                throw ValidationException::withMessages(['amount' => 'El monto debe estar entre $1 y $'.number_format($available, 0, ',', '.').' (lo que queda por reembolsar).']);
            }
            $full = $amount === $available;
            if ($restock && ! $full) {
                throw ValidationException::withMessages(['restock' => 'Los productos se devuelven al stock solo con el reembolso total. Para uno parcial, registra la devolución en Inventario.']);
            }

            $gatewayResult = null;
            if ($this->registry->has($payment->method_code)) {
                $driver = $this->registry->driver($payment->method_code);
                if (! $full && ! in_array('refund_partial', $driver->capabilities(), true)) {
                    throw ValidationException::withMessages(['amount' => 'Esta pasarela solo permite reembolsar el total.']);
                }
                $gatewayResult = $driver->refund($payment, $amount);
                if (! $gatewayResult->ok) {
                    throw ValidationException::withMessages(['amount' => $gatewayResult->message ?? 'La pasarela rechazó el reembolso.']);
                }
            }

            $refund = DB::transaction(function () use ($order, $payment, $amount, $reason, $restock, $full, $gatewayResult, $returnId) {
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
                $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

                $refund = Refund::query()->create([
                    'payment_id' => $payment->id, 'order_id' => $order->id, 'return_id' => $returnId, 'amount' => $amount, 'reason' => $reason,
                    'status' => 'completed', 'external_type' => $gatewayResult?->type, 'authorization_code' => $gatewayResult?->authorizationCode,
                    'restocked' => $restock, 'created_by' => Auth::id(),
                ]);
                $payment->increment('refunded_amount', $amount);
                $refundedTotal = $order->refunded_total + $amount;
                $from = $order->status;
                $newPaymentStatus = $refundedTotal >= $order->paid_total ? 'refunded' : 'partially_refunded';
                $cancel = $full && $order->status === 'processing' && OrderStateMachine::can($order, 'cancelled', ['payment_status' => $newPaymentStatus]);
                $order->update([
                    'refunded_total' => $refundedTotal,
                    'payment_status' => $newPaymentStatus,
                    ...($cancel ? ['status' => 'cancelled', 'cancelled_at' => now()] : []),
                ]);

                if ($restock) {
                    $warehouse = Warehouse::default();
                    foreach ($order->items as $item) {
                        if ($item->variant_id && $this->inventory->item($item->variant_id)->track_stock) {
                            $this->inventory->move($item->variant_id, $warehouse, MovementType::ReturnIn, $item->quantity,
                                "Reembolso del pedido {$order->number}", 'order', $order->uuid);
                        }
                    }
                }

                OrderEvents::record($order, 'refunded', array_filter(['amount' => $amount, 'reason' => $reason, 'restocked' => $restock ?: null]),
                    visible: true, from: $cancel ? $from : null, to: $cancel ? 'cancelled' : null, actor: 'user');
                app(AuditLogger::class)->record('Order.refunded', $order, ['refunded_total' => $refundedTotal - $amount], ['refunded_total' => $refundedTotal, 'amount' => $amount, 'method' => $payment->method_code]);

                return $refund;
            });
        } finally {
            $lock->release();
        }

        Mail::to($order->email)->queue(new RefundMail($order->fresh(), $refund));

        return $refund;
    }
}
