<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use Illuminate\Support\Facades\Auth;

final class OrderEvents
{
    public static function record(Order $order, string $type, array $payload = [], bool $visible = false, ?string $from = null, ?string $to = null, ?string $actor = null): OrderEvent
    {
        $user = Auth::guard('web')->user();

        return OrderEvent::query()->create([
            'order_id' => $order->id,
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actor ?? ($user ? 'user' : 'system'),
            'actor_id' => $actor === 'customer' ? $order->customer_id : $user?->id,
            'payload' => $payload ?: null,
            'visible_to_customer' => $visible,
        ]);
    }
}
