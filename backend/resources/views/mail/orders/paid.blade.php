<x-mail::message>
# Pago confirmado

Hola {{ $order->first_name }}, confirmamos el pago de tu pedido **N.º {{ $order->number }}** por **${{ number_format($order->grand_total, 0, ',', '.') }}**. Ya lo estamos preparando.

@if ($order->latestPayment?->card_last4)
Pagaste con {{ $order->latestPayment->methodLabel() }}, tarjeta terminada en {{ $order->latestPayment->card_last4 }} (código de autorización {{ $order->latestPayment->authorization_code }}).
@endif

@include('mail.orders._items')

<x-mail::button :url="$trackingUrl">
Ver el estado de mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
</x-mail::message>
