<x-mail::message>
# Reembolso del pedido N.º {{ $order->number }}

Hola {{ $order->first_name }}, hicimos un reembolso de **${{ number_format($refund->amount, 0, ',', '.') }}** de tu pedido.

@if ($byCard)
El dinero vuelve a la misma tarjeta con que pagaste. Según tu banco, puede tardar algunos días hábiles en verse en tu estado de cuenta.
@else
Lo devolvimos por transferencia a la cuenta que nos indicaste.
@endif

<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
</x-mail::message>
