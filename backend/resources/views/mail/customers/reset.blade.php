<x-mail::message>
# Hola, {{ $name }}

Recibimos una solicitud para cambiar la contraseña de tu cuenta en {{ $store }}.

<x-mail::button :url="$url">
Elegir una nueva contraseña
</x-mail::button>

El enlace vence en {{ $minutes }} minutos. Si no lo pediste, ignora este mensaje: tu contraseña no cambia.

Saludos,<br>
{{ $store }}
</x-mail::message>
