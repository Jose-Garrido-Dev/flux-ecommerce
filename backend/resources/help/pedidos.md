# Pedidos

**Para qué sirve:** ver los pedidos de la tienda, confirmar las transferencias recibidas y cancelar los que no se pagarán.

## Estados
| Estado del pedido | Significado |
| --- | --- |
| Pendiente de pago | El cliente compró y debe transferir. El stock está reservado |
| En preparación | El pago se confirmó: hay que preparar y despachar |
| Completado | Entregado (se usará desde la fase de logística) |
| Cancelado | Anulado a mano o porque venció el plazo de pago. El stock se liberó |

| Estado del pago | Significado |
| --- | --- |
| Pendiente | Esperando la transferencia |
| Pagado | Confirmaste la transferencia |
| Vencido | Pasó el plazo sin pago (Configuración → Pagos) |
| Anulado | Se canceló el pedido antes de pagar |

## Confirmar una transferencia
1. Revisa en tu banco que llegó el dinero y el monto exacto.
2. Abre el pedido y pulsa **Confirmar pago**. Escribe el monto recibido y, si quieres, el número de operación.
3. Al confirmar: el pedido pasa a *En preparación*, se descuenta el stock (*Salida por venta* en Inventario → Movimientos) y el cliente recibe un correo.

Confirmar dos veces no descuenta dos veces. Si el monto no coincide con el total, no se confirma: revisa con el cliente.

Si el plazo venció y el cliente pagó igual, también puedes confirmarlo: se intenta reservar el stock de nuevo. Si ya no hay, el sistema lo avisa y no confirma.

## Cancelar
Solo los pedidos pendientes de pago. Se libera el stock reservado y el cliente ve el pedido como cancelado. Un pedido pagado se resuelve con un reembolso (fase de pagos).

## Lo que no cambia
Cada pedido guarda una copia fija de los productos, precios, IVA y dirección del momento de la compra. Si después cambias un precio o un nombre en el catálogo, el pedido no cambia.

## Permisos
- Ver pedidos: rol *Ventas*, *Finanzas* o *Administrador de tienda*.
- Confirmar pago: permiso *payments.confirm* (roles *Finanzas* y *Administrador de tienda*).
- Cancelar: permiso *orders.cancel* (roles *Ventas* y *Administrador de tienda*).

## Relaciones con otros módulos
- **Inventario → Reservas** muestra el stock apartado por cada pedido pendiente.
- **Clientes** muestra los pedidos de cada cliente registrado.
