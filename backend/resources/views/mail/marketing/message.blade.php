<x-mail::message>
@if ($preheader)
<span style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $preheader }}</span>
@endif
# {!! $heading !!}

{!! $html !!}

@if (count($items))
<x-mail::table>
| Producto | Cantidad |
| :-- | --: |
@foreach ($items as $item)
| {{ $item['name'] }}{{ $item['variant'] ? ' · '.$item['variant'] : '' }} | {{ $item['quantity'] }} |
@endforeach
</x-mail::table>
@endif

@if ($buttonLabel && $buttonUrl)
<x-mail::button :url="$buttonUrl">
{{ $buttonLabel }}
</x-mail::button>
@endif

Saludos,<br>
{{ $store }}

<x-mail::subcopy>
Recibes este correo porque te suscribiste a las novedades de {{ $store }}. [Darme de baja]({{ $unsubscribeUrl }})
</x-mail::subcopy>
</x-mail::message>
