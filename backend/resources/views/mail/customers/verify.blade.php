<x-mail::message>
# Hola, {{ $name }}

Gracias por crear tu cuenta en {{ $store }}. Confirma tu correo para ver en tu cuenta los pedidos que hiciste antes con este mismo correo.

<x-mail::button :url="$url">
Confirmar mi correo
</x-mail::button>

El enlace vence en 48 horas. Si no creaste esta cuenta, ignora este mensaje.

Saludos,<br>
{{ $store }}
</x-mail::message>
