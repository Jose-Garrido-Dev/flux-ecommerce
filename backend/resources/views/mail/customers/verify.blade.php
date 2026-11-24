<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

<x-mail::button :url="$url">
Confirmar mi correo
</x-mail::button>

El enlace vence en 48 horas. Si no creaste esta cuenta, ignora este mensaje.

Saludos,<br>
{{ $store }}
</x-mail::message>
