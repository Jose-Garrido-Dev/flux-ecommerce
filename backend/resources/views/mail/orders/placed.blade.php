<x-mail::message>
# ¡Gracias por tu compra, {{ $order->first_name }}!

Recibimos tu pedido **N.º {{ $order->number }}**. Para completarlo, transfiere el total a la siguiente cuenta:

<x-mail::panel>
**Monto:** ${{ number_format($order->grand_total, 0, ',', '.') }}<br>
**Banco:** {{ $transfer['bank_name'] }}<br>
**Tipo de cuenta:** {{ $transfer['account_type'] }}<br>
**N.º de cuenta:** {{ $transfer['account_number'] }}<br>
**Titular:** {{ $transfer['account_holder'] }}<br>
**RUT:** {{ $transfer['account_rut'] }}<br>
@if ($transfer['account_email'])
**Correo para el comprobante:** {{ $transfer['account_email'] }}<br>
@endif
**Indica en el comentario:** pedido {{ $order->number }}
</x-mail::panel>

@if ($transfer['instructions'])
{{ $transfer['instructions'] }}
@endif

Reservamos tus productos por **{{ $transfer['hours'] }} horas**. Si no recibimos el pago en ese plazo, el pedido se anula automáticamente.

@include('mail.orders._items')

<x-mail::button :url="$trackingUrl">
Ver el estado de mi pedido
</x-mail::button>

Saludos,<br>
{{ $store }}
</x-mail::message>
