<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

<x-mail::panel>
Vista previa con datos de ejemplo. En el correo real, aquí sigue lo que arma el sistema: {{ $description }}
</x-mail::panel>

Saludos,<br>
{{ $store }}
</x-mail::message>
