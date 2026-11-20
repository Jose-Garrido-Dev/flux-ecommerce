<x-mail::table>
| Producto | Cant. | Total |
|:--|:--:|--:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if ($item->variant_label) ({{ $item->variant_label }})@endif | {{ $item->quantity }} | ${{ number_format($item->line_total, 0, ',', '.') }} |
@endforeach
| Envío: {{ $order->shippingLine?->method_name }} | | {{ $order->shipping_total > 0 ? '$'.number_format($order->shipping_total, 0, ',', '.') : 'Gratis' }} |
| **Total (IVA incluido)** | | **${{ number_format($order->grand_total, 0, ',', '.') }}** |
</x-mail::table>
