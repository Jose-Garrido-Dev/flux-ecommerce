<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

<x-mail::button :url="$url">
Confirmar mi suscripción
</x-mail::button>

El enlace vence en {{ $days }} días. Si no te suscribiste, ignora este mensaje: no te enviaremos nada.

Saludos,<br>
{{ $store }}
</x-mail::message>
