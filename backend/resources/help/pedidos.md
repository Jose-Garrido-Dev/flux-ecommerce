# Pedidos

**Para qué sirve:** ver los pedidos de la tienda, confirmar las transferencias recibidas, revisar los pagos con Webpay, despachar, reembolsar, registrar devoluciones y notas, y cancelar los que no se pagarán.

## Estados
| Estado del pedido | Significado |
| --- | --- |
| Pendiente de pago | El cliente compró y debe pagar (transferencia o Webpay). El stock está reservado |
| En preparación | El pago se confirmó: hay que preparar y despachar |
| Completado | Todos sus despachos fueron entregados o retirados |
| Cancelado | Anulado a mano o porque venció el plazo de pago. El stock se liberó |

| Estado del pago | Significado |
| --- | --- |
| Pendiente | Esperando la transferencia o el pago con Webpay |
| Pagado | Confirmaste la transferencia o Transbank aprobó el pago |
| Reembolsado parcialmente | Se devolvió parte del monto |
| Reembolsado | Se devolvió todo el monto |
| Vencido | Pasó el plazo sin pago (Configuración → Pagos) |
| Anulado | Se canceló el pedido antes de pagar |

| Estado del despacho | Significado |
| --- | --- |
| Por preparar | Aún no se crea ningún despacho |
| En preparación | Hay un despacho en preparación |
| Despachado en parte | Salió una parte; queda algo pendiente |
| Listo para retiro | (Retiro en tienda) El cliente ya puede retirar |
| Despachado | Salió todo |
| Entregado / Retirado | El cliente lo recibió |

## Despachar
Con el pedido pagado y en preparación, usa **Crear despacho** (permiso *shipping.dispatch*, roles *Logística* y *Administrador de tienda*). Puedes despachar todo o una parte. Los despachos aparecen en la sección *Despachos* del pedido y en *Logística → Despachos*. Ver el manual de Despachos.

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
Solo los pedidos pendientes de pago. Escribe el motivo: el cliente lo recibe por correo. Se libera el stock reservado y el pedido queda cancelado. Un pedido pagado se resuelve con un reembolso.

El cliente también puede cancelar su pedido desde la tienda mientras no lo haya pagado.

## Qué cambios de estado se permiten
| Desde | Puede pasar a | Condición |
| --- | --- | --- |
| Pendiente de pago | En preparación | Pago confirmado |
| Pendiente de pago | Cancelado | Aún no se paga |
| En preparación | Completado | Todo entregado o retirado (lo hace el sistema con los despachos) |
| En preparación | Cancelado | Reembolso total y nada despachado |
| Cancelado | En preparación | Solo si se anuló por plazo vencido y llega el pago (por ejemplo, un pago tardío con Webpay) |
| Completado | — | Lo que vuelve después se gestiona como devolución |

Cualquier otro cambio se rechaza con un mensaje que explica por qué. Si un cliente paga con Webpay un pedido que cancelaste a mano, el pago queda registrado con una nota para reembolsarlo.

## Notas
**Agregar nota** guarda un comentario en el historial del pedido. Si marcas *El cliente la ve en su pedido*, aparece en la página del pedido del cliente; si no, solo la ve el equipo.

## Devoluciones
**Registrar devolución** crea una solicitud de devolución o cambio para un pedido pagado (por ejemplo, si el cliente llamó). La sección *Devoluciones* del pedido muestra las solicitudes y su estado. Ver el manual de Devoluciones y cambios.

## Correos del pedido
La sección *Correos* (cerrada al inicio) muestra los correos enviados al cliente por este pedido, con su estado. Para reenviar uno, usa *Correos → Historial*.

## Lo que no cambia
Cada pedido guarda una copia fija de los productos, precios, IVA y dirección del momento de la compra. Si después cambias un precio o un nombre en el catálogo, el pedido no cambia.

## Permisos
- Ver pedidos: rol *Ventas*, *Finanzas* o *Administrador de tienda*.
- Confirmar pago: permiso *payments.confirm* (roles *Finanzas* y *Administrador de tienda*).
- Cancelar: permiso *orders.cancel* (roles *Ventas* y *Administrador de tienda*).
- Agregar nota: permiso *orders.update*.
- Registrar devolución: permiso *returns.create*.
- Consultar en Webpay: permiso *payments.reconcile*.
- Reembolsar: permiso *refunds.process* (roles *Finanzas* y *Administrador de tienda*).

## Relaciones con otros módulos
- **Inventario → Reservas** muestra el stock apartado por cada pedido pendiente.
- **Clientes** muestra los pedidos de cada cliente registrado.
- **Logística → Despachos e Incidencias** muestran los envíos de cada pedido.
- **Ventas → Devoluciones** muestra las devoluciones y cambios.
- **Correos → Historial** muestra cada correo enviado.
