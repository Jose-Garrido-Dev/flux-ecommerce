<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

@include('mail.orders._items')

<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
</x-mail::message>
