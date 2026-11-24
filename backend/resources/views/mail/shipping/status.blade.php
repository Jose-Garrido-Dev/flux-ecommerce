<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

@if ($kind === 'ready_for_pickup' && $order->shippingLine?->pickup_address)
**Dirección:** {{ $order->shippingLine->pickup_address }}
@endif
@if ($kind === 'shipped' && $shipment->tracking_number)
**Número de seguimiento:** {{ $shipment->tracking_number }}
@endif

<x-mail::table>
| Producto | Cantidad |
|:--|--:|
@foreach ($shipment->items as $line)
| {{ $line->orderItem->product_name }}{{ $line->orderItem->variant_label ? ' ('.$line->orderItem->variant_label.')' : '' }} | {{ $line->quantity }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$followUrl">
Seguir mi envío
</x-mail::button>

@if ($courierUrl && $kind === 'shipped')
También puedes verlo en la [página de {{ $shipment->carrier_name }}]({{ $courierUrl }}).
@endif

Saludos,<br>
{{ $store }}
</x-mail::message>
