<x-mail::message>
# Nuevo pedido N.º {{ $order->number }}

**Cliente:** {{ $order->fullName() }} ({{ $order->email }})<br>
**Pago:** transferencia bancaria, pendiente de confirmar.

@include('mail.orders._items')

<x-mail::button :url="$adminUrl">
Ver el pedido en el panel
</x-mail::button>
</x-mail::message>
