# Despachos

**Para qué sirve:** registrar cada envío de un pedido, avisar al cliente y seguirlo hasta la entrega.

## Crear un despacho
Desde el pedido (pagado y en preparación) usa **Crear despacho**:
- Elige cuántas unidades de cada producto van. Puedes despachar en partes: lo que falte queda pendiente para otro despacho.
- Elige el courier (o despacho propio), número de seguimiento, bultos, peso y lo que te cobró el courier (solo uso interno).

En pedidos con *Retiro en tienda* el despacho se usa para preparar el retiro.

## Estados
| Estado | Qué significa | Correo al cliente |
| --- | --- | --- |
| En preparación | Se está armando | No |
| Listo para retiro | (Retiro en tienda) El cliente ya puede ir | Sí |
| Despachado | Salió con el courier | Sí, con el número y el enlace |
| En tránsito / En reparto | Avance informado por el courier | No |
| Intento fallido | No se pudo entregar | No |
| Entregado / Retirado | Terminado | Sí |
| Devuelto a la tienda / Anulado | Terminado sin entrega | No |

Solo se ofrecen los cambios de estado posibles. Cuando todos los despachos están entregados o retirados, el pedido pasa a **Completado**.

## Seguimiento para el cliente
- En su pedido ve cada despacho con su línea de tiempo.
- El correo trae un enlace de seguimiento que se puede compartir: muestra estado, productos y comuna, nunca nombres ni dirección.

## Incidencias
Usa **Registrar incidencia** (atraso, daño, extravío, dirección errónea…). Se gestionan en *Logística → Incidencias*. El cliente no las ve.
