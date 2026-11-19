# Movimientos de inventario

**Para qué sirve:** el historial de todo lo que cambió el stock: entradas, salidas, ventas, ajustes, transferencias, reservas y liberaciones.

## Columnas
| Columna | Explicación |
| --- | --- |
| Tipo | Entrada por compra, entrada manual, devolución, ajuste, salida por venta, salida manual, merma, transferencia, reserva, liberación o contramovimiento |
| Cantidad | Positiva suma, negativa resta. En reservas es 0 (no cambia el físico; se ve en la columna *Reserva*) |
| Antes / Después | Stock físico antes y después del movimiento |
| Motivo y referencia | Por qué se hizo y el documento de respaldo (factura, conteo, transferencia, pedido) |
| Usuario | Quién lo registró. *Sistema* si fue automático |

## Corregir un error
Los movimientos **no se editan ni se borran**. Usa **Revertir**: se crea un *contramovimiento* que anula el efecto y queda enlazado al original. Cada movimiento se revierte una sola vez. Las ventas, transferencias y reservas no se revierten desde aquí: se corrigen con su propio proceso (devolución, nueva transferencia, liberar reserva).

## Filtros
Por tipo, bodega, producto y rango de fechas.

## Relaciones con otros módulos
- **Existencias → Historial** abre esta lista filtrada por producto.
- **Auditoría** registra además quién hizo cada movimiento manual.
