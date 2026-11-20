# Pedidos

**Para qué sirve:** ver los pedidos de la tienda, confirmar las transferencias recibidas, revisar los pagos con Webpay, reembolsar y cancelar los que no se pagarán.

## Estados
| Estado del pedido | Significado |
| --- | --- |
| Pendiente de pago | El cliente compró y debe pagar (transferencia o Webpay). El stock está reservado |
| En preparación | El pago se confirmó: hay que preparar y despachar |
| Completado | Entregado (se usará desde la fase de logística) |
| Cancelado | Anulado a mano o porque venció el plazo de pago. El stock se liberó |

| Estado del pago | Significado |
| --- | --- |
| Pendiente | Esperando la transferencia o el pago con Webpay |
| Pagado | Confirmaste la transferencia o Transbank aprobó el pago |
| Reembolsado parcialmente | Se devolvió parte del monto |
| Reembolsado | Se devolvió todo el monto |
| Vencido | Pasó el plazo sin pago (Configuración → Pagos) |
| Anulado | Se canceló el pedido antes de pagar |

## Confirmar una transferencia
1. Revisa en tu banco que llegó el dinero y el monto exacto.
2. Abre el pedido y pulsa **Confirmar pago**. Escribe el monto recibido y, si quieres, el número de operación.
3. Al confirmar: el pedido pasa a *En preparación*, se descuenta el stock (*Salida por venta* en Inventario → Movimientos) y el cliente recibe un correo.

Confirmar dos veces no descuenta dos veces. Si el monto no coincide con el total, no se confirma: revisa con el cliente.

Si el plazo venció y el cliente pagó igual, también puedes confirmarlo: se intenta reservar el stock de nuevo. Si ya no hay, el sistema lo avisa y no confirma.

## Pagos con Webpay
La sección **Pagos** del pedido muestra cada intento: estado (Aprobado, Rechazado, No completado), orden de compra, tarjeta (4 últimos dígitos), tipo de pago, cuotas y código de autorización. No hay que confirmar nada a mano: el pago se confirma con Transbank.

Si un pago quedó *Pendiente* (por ejemplo, el cliente cerró el navegador en Webpay), pulsa **Consultar en Webpay** para preguntar a Transbank su estado. El sistema también lo hace solo cada minuto.

## Reembolsar
Para pedidos pagados. Pulsa **Reembolsar**, escribe el monto y el motivo.
- Con Webpay, el reembolso se pide a Transbank en el momento: si Transbank lo rechaza, no cambia nada. El mismo día de la compra suele ser una reversa (no aparece en el estado de cuenta); después, una anulación.
- Con transferencia, el sistema solo registra el reembolso: la devolución del dinero la haces tú desde el banco.
- Puedes hacer varios reembolsos parciales hasta completar el total pagado.
- **Devolver el stock** solo está disponible en un reembolso total. Si el pedido aún no se despachaba, un reembolso total también lo cancela.
- El cliente recibe un correo con el monto reembolsado.

## Cancelar
Solo los pedidos pendientes de pago. Se libera el stock reservado y el cliente ve el pedido como cancelado. Un pedido pagado se resuelve con un reembolso.

## Lo que no cambia
Cada pedido guarda una copia fija de los productos, precios, IVA y dirección del momento de la compra. Si después cambias un precio o un nombre en el catálogo, el pedido no cambia.

## Permisos
- Ver pedidos: rol *Ventas*, *Finanzas* o *Administrador de tienda*.
- Confirmar pago: permiso *payments.confirm* (roles *Finanzas* y *Administrador de tienda*).
- Cancelar: permiso *orders.cancel* (roles *Ventas* y *Administrador de tienda*).
- Consultar en Webpay: permiso *payments.reconcile*.
- Reembolsar: permiso *refunds.process* (roles *Finanzas* y *Administrador de tienda*).

## Relaciones con otros módulos
- **Inventario → Reservas** muestra el stock apartado por cada pedido pendiente.
- **Clientes** muestra los pedidos de cada cliente registrado.
