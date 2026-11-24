<x-mail::message>
@switch($kind)
@case('ready_for_pickup')
# Tu pedido está listo para retiro

Hola {{ $order->first_name }}, ya puedes retirar tu pedido N.º {{ $order->number }}.

@if ($order->shippingLine?->pickup_address)
**Dirección:** {{ $order->shippingLine->pickup_address }}
@endif

Lleva tu número de pedido y un documento de identidad.
@break
@case('delivered')
# Entregamos tu pedido

Hola {{ $order->first_name }}, tu pedido N.º {{ $order->number }} fue entregado. ¡Gracias por tu compra!
@break
@case('picked_up')
# Retiraste tu pedido

Hola {{ $order->first_name }}, registramos el retiro de tu pedido N.º {{ $order->number }}. ¡Gracias por tu compra!
@break
@default
# Tu pedido va en camino

Hola {{ $order->first_name }}, tu pedido N.º {{ $order->number }} salió con **{{ $shipment->carrier_name }}**.

@if ($shipment->tracking_number)
**Número de seguimiento:** {{ $shipment->tracking_number }}
@endif
@endswitch

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
