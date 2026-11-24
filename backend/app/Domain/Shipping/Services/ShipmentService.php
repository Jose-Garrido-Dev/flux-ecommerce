<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderEvents;
use App\Domain\Shipping\Mail\ShipmentMail;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentEvent;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\System\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Despachos de un pedido: crear (total o parcial), avanzar estados con transiciones permitidas,
 * número de seguimiento e incidencias. Mantiene al día el estado de despacho del pedido.
 */
class ShipmentService
{
    /** Cantidad pendiente de despachar por ítem del pedido. @return array<int, int> order_item_id => cantidad */
    public function remaining(Order $order): array
    {
        $order->loadMissing('items');
        $sent = ShipmentItem::query()
            ->whereHas('shipment', fn ($q) => $q->where('order_id', $order->id)->where('status', '!=', 'cancelled'))
            ->selectRaw('order_item_id, sum(quantity) as qty')->groupBy('order_item_id')->pluck('qty', 'order_item_id');

        $left = [];
        foreach ($order->items as $item) {
            $left[$item->id] = max(0, $item->quantity - (int) ($sent[$item->id] ?? 0));
        }

        return $left;
    }

    /**
     * @param  array<int, int>  $items  order_item_id => cantidad (vacío = todo lo pendiente)
     * @param  array{carrier_code?: ?string, tracking_number?: ?string, tracking_url?: ?string, packages?: ?int, weight_g?: ?int, cost_amount?: ?int, notes?: ?string}  $data
     */
    public function create(Order $order, array $items, array $data = []): Shipment
    {
        return DB::transaction(function () use ($order, $items, $data) {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'processing' || ! in_array($order->payment_status, ['paid', 'partially_refunded'], true)) {
                throw ValidationException::withMessages(['items' => 'Solo se despachan pedidos pagados que están en preparación.']);
            }
            $left = $this->remaining($order);
            $items = array_filter($items === [] ? $left : $items, fn ($q) => (int) $q > 0);
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'No queda nada por despachar en este pedido.']);
            }
            foreach ($items as $itemId => $qty) {
                if (! array_key_exists((int) $itemId, $left) || (int) $qty > $left[(int) $itemId]) {
                    throw ValidationException::withMessages(['items' => 'Las cantidades superan lo que falta por despachar.']);
                }
            }

            $pickup = $order->shippingLine?->type === 'pickup';
            $courier = $pickup ? null : Courier::for($data['carrier_code'] ?? $order->shippingLine?->carrier_code);
            $uuid = (string) Str::uuid();
            $shipment = Shipment::query()->create([
                'uuid' => $uuid,
                'order_id' => $order->id,
                'tracking_token_hash' => ShipmentTokens::hash(ShipmentTokens::for($uuid)),
                'carrier_code' => $courier?->code,
                'carrier_name' => $pickup ? 'Retiro en tienda' : ($courier?->name ?? 'Despacho propio'),
                'tracking_number' => $data['tracking_number'] ?? null,
                'tracking_url' => $data['tracking_url'] ?? null,
                'status' => 'preparing',
                'packages' => max(1, (int) ($data['packages'] ?? 1)),
                'weight_g' => $data['weight_g'] ?? null,
                'cost_amount' => $data['cost_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
            foreach ($items as $itemId => $qty) {
                $shipment->items()->create(['order_item_id' => (int) $itemId, 'quantity' => (int) $qty]);
            }
            $this->event($shipment, 'preparing', 'Estamos preparando tu pedido.');
            OrderEvents::record($order, 'shipment_created', ['shipment' => $shipment->uuid, 'carrier' => $shipment->carrier_name], true);
            $this->syncOrder($order);

            return $shipment;
        });
    }

    /** Avanza el estado del despacho. Un evento del courier con el mismo identificador no se aplica dos veces. */
    public function updateStatus(Shipment $shipment, string $status, ?string $description = null, ?string $location = null, ?Carbon $occurredAt = null, string $source = 'admin', ?string $externalId = null): Shipment
    {
        $mail = null;
        $shipment = DB::transaction(function () use ($shipment, $status, $description, $location, $occurredAt, $source, $externalId, &$mail) {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($externalId !== null && $shipment->events()->where('external_event_id', $externalId)->exists()) {
                return $shipment;
            }
            if (! array_key_exists($status, Shipment::STATUSES) || ! $shipment->canMoveTo($status)) {
                throw ValidationException::withMessages(['status' => sprintf(
                    'Un despacho en estado "%s" no puede pasar a "%s".', $shipment->statusLabel(), Shipment::STATUSES[$status] ?? $status,
                )]);
            }
            if ($status === 'ready_for_pickup' && $shipment->order->shippingLine?->type !== 'pickup') {
                throw ValidationException::withMessages(['status' => 'Este pedido no es para retiro en tienda.']);
            }
            if ($status === 'shipped' && $shipment->order->shippingLine?->type === 'pickup') {
                throw ValidationException::withMessages(['status' => 'Este pedido es para retiro en tienda: márcalo como listo para retiro.']);
            }
            $from = $shipment->status;
            $at = $occurredAt ?? now();
            $shipment->status = $status;
            if ($status === 'shipped' && ! $shipment->shipped_at) {
                $shipment->shipped_at = $at;
            }
            if (in_array($status, ['delivered', 'picked_up'], true)) {
                $shipment->delivered_at = $at;
            }
            $shipment->save();
            $this->event($shipment, $status, $description, $location, $at, $source, $externalId);
            OrderEvents::record($shipment->order, 'shipment_status', ['shipment' => $shipment->uuid, 'status' => $status], true, $from, $status, $source === 'courier' ? 'provider' : null);
            $this->syncOrder($shipment->order);
            if (in_array($status, ['shipped', 'ready_for_pickup', 'delivered', 'picked_up'], true)) {
                $mail = $status;
            }

            return $shipment;
        });
        if ($mail) {
            Mail::to($shipment->order->email)->queue(new ShipmentMail($shipment->order, $shipment, $mail));
        }

        return $shipment;
    }

    public function setTracking(Shipment $shipment, ?string $number, ?string $url, ?string $carrierCode = null): Shipment
    {
        if ($shipment->isFinal()) {
            throw ValidationException::withMessages(['tracking_number' => 'El despacho ya terminó: no se puede cambiar el seguimiento.']);
        }
        // null = no cambia el courier; '' = despacho propio
        $carrier = [];
        if ($carrierCode !== null && $shipment->carrier_name !== 'Retiro en tienda') {
            $courier = Courier::for($carrierCode);
            $carrier = ['carrier_code' => $courier?->code, 'carrier_name' => $courier?->name ?? 'Despacho propio'];
        }
        $shipment->fill(['tracking_number' => $number ?: null, 'tracking_url' => $url ?: null, ...$carrier])->save();

        return $shipment;
    }

    public function openIncident(Shipment $shipment, string $type, string $description, ?int $ownerId = null): ShippingIncident
    {
        if (! array_key_exists($type, ShippingIncident::TYPES)) {
            throw ValidationException::withMessages(['type' => 'Tipo de incidencia no válido.']);
        }
        $incident = ShippingIncident::query()->create([
            'shipment_id' => $shipment->id, 'order_id' => $shipment->order_id, 'type' => $type, 'description' => $description,
            'owner_user_id' => $ownerId, 'created_by' => Auth::id(),
        ]);
        OrderEvents::record($shipment->order, 'incident_opened', ['incident' => $incident->id, 'type' => $type]);

        return $incident;
    }

    public function resolveIncident(ShippingIncident $incident, string $status, ?string $resolution): ShippingIncident
    {
        if ($status === 'resolved' && blank($resolution)) {
            throw ValidationException::withMessages(['resolution' => 'Describe cómo se resolvió.']);
        }
        $incident->update(['status' => $status, 'resolution' => $resolution, 'resolved_at' => $status === 'resolved' ? now() : null]);
        if ($status === 'resolved') {
            OrderEvents::record($incident->order, 'incident_resolved', ['incident' => $incident->id]);
        }

        return $incident;
    }

    public function trackingUrl(Shipment $shipment): ?string
    {
        return $shipment->tracking_url ?: Courier::for($shipment->carrier_code)?->trackingUrl($shipment->tracking_number);
    }

    private function event(Shipment $shipment, string $status, ?string $description = null, ?string $location = null, ?Carbon $at = null, string $source = 'admin', ?string $externalId = null): void
    {
        ShipmentEvent::query()->create([
            'shipment_id' => $shipment->id, 'status' => $status, 'description' => $description, 'location' => $location,
            'source' => $source, 'external_event_id' => $externalId, 'user_id' => $source === 'admin' ? Auth::id() : null,
            'occurred_at' => $at ?? now(),
        ]);
    }

    /** Estado de despacho del pedido según sus despachos; con todo entregado o retirado, el pedido se completa. */
    private function syncOrder(Order $order): void
    {
        $order = $order->fresh(['items']);
        $shipments = Shipment::query()->where('order_id', $order->id)->where('status', '!=', 'cancelled')->with('items')->get();
        $left = array_sum($this->remaining($order));
        $done = $shipments->filter(fn ($s) => in_array($s->status, ['delivered', 'picked_up'], true));
        $moving = $shipments->filter(fn ($s) => ! in_array($s->status, ['preparing', 'ready_for_pickup', 'returned'], true));

        $fulfillment = match (true) {
            $shipments->isEmpty() => 'unfulfilled',
            $left === 0 && $done->count() === $shipments->count() => $order->shippingLine?->type === 'pickup' ? 'picked_up' : 'delivered',
            $left === 0 && $shipments->every(fn ($s) => $s->status === 'ready_for_pickup' || in_array($s->status, ['picked_up'], true)) => 'ready_for_pickup',
            $moving->isNotEmpty() && $left === 0 => 'shipped',
            $moving->isNotEmpty() => 'partially_shipped',
            default => 'preparing',
        };
        $changes = ['fulfillment_status' => $fulfillment];
        if (in_array($fulfillment, ['delivered', 'picked_up'], true) && $order->status === 'processing') {
            $changes['status'] = 'completed';
        }
        $before = $order->only(array_keys($changes));
        $order->update($changes);
        if (isset($changes['status'])) {
            OrderEvents::record($order, 'completed', [], true, 'processing', 'completed');
            app(AuditLogger::class)->record('Order.completed', $order, $before, $changes);
        }
    }
}
