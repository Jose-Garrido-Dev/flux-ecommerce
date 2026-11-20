# Medios de pago

**Para qué sirve:** configurar cómo te pagan los clientes. En esta versión: transferencia bancaria.

## Transferencia bancaria
| Campo | Explicación |
| --- | --- |
| Ofrecer transferencia bancaria | Si está apagado o faltan datos de la cuenta, el checkout no ofrece este medio |
| Banco, tipo y número de cuenta, titular, RUT | Lo que ve el cliente para transferir |
| Correo para el comprobante | Opcional |
| Plazo para pagar | Horas que el stock queda reservado. Si no confirmas el pago en ese plazo, el pedido se anula y el stock se libera (48 por defecto) |
| Instrucciones adicionales | Texto libre que se muestra junto a los datos |

## Cómo funciona
1. El cliente compra y ve los datos de la cuenta en pantalla y en el correo del pedido.
2. Transfiere indicando el número de pedido.
3. Tú revisas el banco y confirmas el pago en *Ventas → Pedidos*.

## Pasarelas
Webpay Plus, Mercado Pago, Flow y Khipu se integran en la fase de pagos y se activan solo después de probarlas con sus credenciales de prueba.
