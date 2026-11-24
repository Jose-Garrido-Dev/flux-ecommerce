# Métodos de envío

**Para qué sirve:** definir cómo recibe el cliente su compra y cuánto paga por el envío. La tienda no puede vender si no hay al menos un método activo.

## Tipos
| Tipo | Explicación |
| --- | --- |
| Tarifa fija | Un precio por pedido en las regiones elegidas (sin regiones = todo Chile) |
| Tarifas por zona y peso | El precio sale de una tabla: zona de destino y rango de peso. Las zonas se crean en *Logística → Zonas de envío* |
| Retiro en tienda | El cliente retira en tu local. Indica dirección, horario e instrucciones (si no pones dirección se usa la de *Configuración → Datos del negocio*) |

Viene creado *Retiro en tienda*, **desactivado**: actívalo si tienes local.

## Campos importantes
| Campo | Explicación |
| --- | --- |
| Courier | Con quién envías (Chilexpress, Starken, Blue Express). Vacío = despacho propio. Solo aparecen los couriers marcados en *Logística → Couriers* |
| Gratis desde | Monto de productos desde el cual el envío es gratis (el monto exacto ya es gratis) |
| Factor volumétrico | Para cobrar paquetes livianos pero grandes. Ej.: 4000 = largo × ancho × alto en cm dividido por 4000 da los kg volumétricos. Se cobra el mayor entre peso real y volumétrico. Vacío = solo peso real |
| Peso máximo | Sobre ese peso el método no se ofrece |
| Admite cadena de frío | Si un producto requiere cadena de frío, solo se ofrecen los métodos con esta opción |

## Tarifas por zona y peso
Cada fila es: zona, peso *desde*, peso *hasta*, precio y plazo. Ambos límites están incluidos: un tramo de 0 a 1 kg incluye 1 kg exacto; el siguiente empieza en 1,001 kg. Deja *Hasta* vacío para "sin límite". Una fila sin zona vale para cualquier zona que no tenga un tramo propio para ese peso.

El peso del pedido es la suma de los pesos de las variantes (ficha del producto → pestaña Envío). **Carga el peso y las medidas de tus productos**: sin peso se calculan como 0 kg.

## Ejemplos
- Un precio para todo Chile: tarifa fija de $3.990 sin regiones.
- Courier por zonas: zona "Santiago" ($2.990 hasta 2 kg, $4.990 hasta 10 kg) y zona "Regiones" ($5.990 hasta 2 kg, $8.990 hasta 10 kg).
- Envío gratis desde $50.000: completa *Gratis desde*.

Para comprobar qué ve el cliente usa *Logística → Simulador de tarifas*.

## Reglas
- El precio incluye IVA.
- Un método usado en pedidos no se elimina: desactívalo.
- El cliente solo ve los métodos que sirven para su comuna y sus productos. Al pagar, el servidor vuelve a calcular el envío.
