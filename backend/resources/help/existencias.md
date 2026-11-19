# Existencias

**Para qué sirve:** ver cuánto stock hay de cada producto y variante en cada bodega, y registrar entradas, salidas, ajustes y conteos.

## Columnas
| Columna | Explicación |
| --- | --- |
| Físico | Unidades que están en la bodega |
| Reservado | Unidades apartadas por carritos o pedidos pendientes de pago |
| Disponible | Físico menos reservado: lo que se puede vender |
| Mínimo | Bajo este número el producto aparece en *Stock bajo* |
| Estado | *Con stock*, *Stock bajo*, *Sin stock*, *Con sobreventa* o *Sin control de stock* |

## Tarjetas de alerta
Arriba de la lista: sin stock, stock bajo, reservas vencidas sin liberar, productos sin movimientos en 90 días, diferencias de inventario (ajustes por conteo en 30 días), el producto de mayor rotación y los que permiten sobreventa. Al hacer clic se abre la lista filtrada. Las alertas cuentan productos (variantes), sumando todas las bodegas activas.

## Acciones
- **Registrar movimiento** (arriba): elige producto, bodega, tipo (entrada por compra, entrada manual, devolución, salida manual, merma, ajuste positivo o negativo), cantidad y motivo. La cantidad siempre es positiva: el tipo define si suma o resta.
- **Acciones → Movimiento:** lo mismo, para la fila elegida.
- **Acciones → Conteo:** escribe cuántas unidades contaste; la diferencia se registra como ajuste.
- **Acciones → Configurar:** controlar stock, permitir sobreventa y stock mínimo de esa variante.
- **Acciones → Historial:** abre los movimientos de esa variante.

## Reglas
- El stock no se edita directo. Cada cambio deja un movimiento con antes, después, motivo, usuario y fecha.
- Una salida no puede dejar el stock bajo lo reservado.
- Dos compras simultáneas nunca venden la misma última unidad: la reserva es atómica en la base de datos.

## Errores frecuentes
- *"Solo hay N unidades disponibles en esa bodega":* hay menos stock libre del que intentas sacar (puede haber unidades reservadas).
- *"El conteo no puede ser menor" a lo reservado:* libera reservas primero en *Inventario → Reservas*.

## Relaciones con otros módulos
- **Productos:** pestaña *Inventario* y botón *Inventario* en cada variante.
- **Tienda:** muestra "Disponible", "¡Quedan N unidades!" o "Agotado" según el disponible.
