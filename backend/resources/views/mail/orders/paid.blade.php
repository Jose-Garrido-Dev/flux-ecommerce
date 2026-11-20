<x-mail::message>
# Pago confirmado

Hola {{ $order->first_name }}, confirmamos el pago de tu pedido **N.º {{ $order->number }}**. Ya lo estamos preparando.

@include('mail.orders._items')

<x-mail::button :url="$trackingUrl">
Ver el estado de mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
</x-mail::message>
