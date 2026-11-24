# Cupones

**Para qué sirve:** crear códigos de descuento que el cliente escribe en el carrito o en el pago. Está en *Marketing → Cupones*.

## Tipos
| Tipo | Qué hace |
| --- | --- |
| Porcentaje | Descuenta un % de los productos que participan. Puedes poner un **descuento máximo** en pesos. |
| Monto fijo | Descuenta un monto en pesos, repartido entre los productos que participan. Nunca deja un producto bajo $0. |
| Envío gratis | El despacho queda en $0. No descuenta productos. |

## Condiciones
- **Compra mínima:** se mide sobre el total de productos del carrito, antes de descuentos.
- **Desde / Hasta:** fuera de esas fechas el cupón no se acepta. El cliente ve "Este cupón todavía no está vigente" o "Este cupón venció".
- **Usos totales:** cuántos pedidos pueden usarlo en total. Un pedido cancelado libera su uso.
- **Usos por cliente:** se cuenta por correo (con o sin cuenta).
- **Solo para la primera compra:** se rechaza si el correo o la cuenta ya tiene un pedido (salvo pedidos cancelados).
- **Productos que participan:** sin selección participa todo el carrito. Con categorías se incluyen sus subcategorías. Una exclusión gana sobre una inclusión.

## Combinar con promociones
Si el cupón **se combina con promociones automáticas**, se aplica después de las promociones, sobre el precio ya rebajado.
Si no se combina, la tienda calcula ambos y aplica **el descuento que más le conviene al cliente**, avisándole cuando el cupón no le sirve.

## Qué pasa al pagar
El cupón se vuelve a revisar dentro de la transacción del pedido (con bloqueo): si dos personas usan el último cupo al mismo tiempo, solo una lo obtiene y la otra ve el motivo. El pedido guarda el descuento por producto, de modo que una devolución reembolsa lo que el cliente realmente pagó.

## Eliminar o pausar
Un cupón que ya se usó no se puede eliminar (los pedidos lo referencian): usa **Desactivar**.
