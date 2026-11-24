<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

@if ($kind === 'approved' && $returnRequest->instructions)
<x-mail::panel>
{{ $returnRequest->instructions }}
</x-mail::panel>
@endif

<x-mail::table>
| Producto | Cantidad |
|:--|--:|
@foreach ($returnRequest->items as $line)
| {{ $line->orderItem->product_name }}{{ $line->orderItem->variant_label ? ' ('.$line->orderItem->variant_label.')' : '' }} | {{ $line->quantity }} |
@endforeach
</x-mail::table>

@if ($kind === 'new_admin')
<x-mail::button :url="$adminUrl">
Revisar en el panel
</x-mail::button>
@else
<x-mail::button :url="$trackingUrl">
Ver mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
@endif
</x-mail::message>
