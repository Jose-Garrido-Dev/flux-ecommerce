# Reservas de stock

**Para qué sirve:** ver el stock apartado por clientes que están comprando. Mientras una reserva está activa, esas unidades no se pueden vender a otra persona.

## Cómo funcionan
- Al agregar al carrito o iniciar el pago se reserva el stock (desde la fase de carrito y checkout).
- La reserva dura lo configurado en *Configuración → Inventario* (30 minutos por defecto).
- Si el pago se confirma, la reserva pasa a *Vendida* y se descuenta el stock físico. Un aviso de pago repetido no descuenta dos veces.
- Si vence, el programador de tareas la libera sola cada minuto (estado *Vencida*).

## Estados
| Estado | Significado |
| --- | --- |
| Activa | Stock apartado |
| Vendida | Se convirtió en venta |
| Liberada | Se liberó a mano o al vaciar el carrito |
| Vencida | Pasó el tiempo sin pagar y se liberó sola |

## Acciones
- **Liberar:** devuelve las unidades al disponible. Pide un motivo.

## Advertencias
- Si la alerta *Reservas vencidas sin liberar* muestra un número mayor a 0, el programador de tareas no está corriendo. Revisa el contenedor `scheduler` (ver la guía de instalación).
