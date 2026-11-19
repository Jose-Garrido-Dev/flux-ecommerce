# Bodegas

**Para qué sirve:** registrar los lugares donde guardas stock (bodega central, tienda física, bodega de un tercero). Cada variante tiene un stock por bodega.

## Campos
| Campo | Explicación |
| --- | --- |
| Nombre | Ej.: "Bodega Quilicura" |
| Código | Corto y sin espacios, se guarda en mayúsculas. Ej.: `QUILICURA` |
| Dirección, región, comuna, teléfono | Datos de contacto y despacho |
| Responsable | Usuario del panel a cargo de la bodega |
| Estado | *Activa* o *Inactiva*. El stock de una bodega inactiva no se ofrece en la tienda |
| Prioridad | Orden en que se toma el stock al reservar: primero la principal y luego de menor a mayor prioridad |
| Bodega principal | Recibe el stock de productos nuevos y de las importaciones. Hay solo una |

## Crear, modificar y desactivar
- **Nueva bodega**, completa y guarda. Para que sea la principal, marca *Bodega principal*: la anterior deja de serlo.
- La principal no se puede desmarcar ni desactivar directamente: marca otra como principal.
- **Eliminar** solo funciona si la bodega no tiene stock ni movimientos. Si los tiene, desactívala: el historial se conserva.

## Ejemplos
- Tienda con una sola bodega: usa la *Bodega principal* que viene creada y cambia su nombre y dirección.
- Bodega central + tienda física: la central como principal (prioridad 0) y la tienda con prioridad 10. Las ventas en línea toman primero de la central.

## Relaciones con otros módulos
- **Existencias** muestra el stock de cada producto en cada bodega.
- **Transferencias** mueve stock de una bodega a otra.
- **Despachos** (fase de logística) usará la dirección de la bodega como origen.
