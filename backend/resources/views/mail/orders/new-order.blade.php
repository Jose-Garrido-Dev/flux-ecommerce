<x-mail::message>
# {!! $tpl['heading'] !!}

{!! $tpl['intro'] !!}

**Cliente:** {{ $order->fullName() }} ({{ $order->email }})<br>
@if ($order->payment_status === 'paid')
**Pago:** {{ $order->latestPayment?->methodLabel() }}, pagado.
@else
**Pago:** transferencia bancaria, pendiente de confirmar.
@endif

@include('mail.orders._items')

<x-mail::button :url="$adminUrl">
Ver el pedido en el panel
</x-mail::button>
</x-mail::message>
