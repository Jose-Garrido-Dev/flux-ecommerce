# Productos

**Para qué sirve:** crear y mantener todo lo que vendes: nombre, precios, imágenes, variantes (tallas, colores), especificaciones, envío y SEO.

## Secciones
- **Listado:** busca por nombre, SKU o código de barras. Filtra por estado, marca, categoría, precio, destacados, con variantes o sin imágenes.
- **Formulario:** pestañas *General*, *Precios*, *Especificaciones*, *Envío*, *Inventario* y *SEO*.
- **Pestañas extra al editar:** *Variantes*, *Imágenes* y *Relacionados* aparecen una vez creado el producto.

## Campos principales
| Campo | Explicación |
| --- | --- |
| Nombre | Como lo verá el cliente. Ej.: "Polera de algodón orgánico" |
| URL amigable | Dirección de la ficha: `/producto/polera-de-algodon-organico`. Se genera sola |
| SKU | Código interno único. Solo letras, números, punto y guiones |
| Código de barras | EAN/UPC si lo tiene. No puede repetirse |
| Estado | *Borrador* (no se ve), *Publicado* (se ve en la tienda), *Archivado* (se oculta y se conserva) |
| Fecha de publicación | Si es futura, el producto aparece ese día |
| Precio de venta | Con IVA incluido, en pesos sin decimales |
| Precio anterior | Precio tachado. Debe ser mayor al precio de venta |
| Precio promocional | Debe ser menor al precio de venta. Puede tener fecha de inicio y fin |
| Costo | Interno, nunca se muestra. Sirve para calcular el margen (sobre el precio sin IVA) |
| Impuesto | IVA 19 % o exento |
| Peso y medidas | Se usan para calcular el despacho |

## Crear, modificar y desactivar
1. Pulsa **Nuevo producto**, completa *General* y *Precios* y guarda.
2. Se abre la edición: sube imágenes en **Imágenes** y, si tiene tallas o colores, crea las combinaciones en **Variantes → Generar combinaciones**.
3. Para publicar, elige estado *Publicado* o usa el botón **Publicar**. Solo se publica si tiene al menos una variante activa con precio.
4. Para sacarlo de la tienda: **Despublicar** (vuelve a borrador) o **Archivar** (lo guarda con todo su historial).
5. **Eliminar** solo funciona si el producto nunca se vendió. Si tiene pedidos, archívalo.

## Variantes
- Las opciones (Color, Talla…) se configuran primero en *Catálogo → Atributos*, marcando "Se usa para variantes".
- **Generar combinaciones:** marca los valores (ej.: Negro, Blanco × S, M, L) y se crean 6 variantes con SKU `PREFIJO-NEGRO-S`. Las que ya existen se omiten. Máximo 300 por vez.
- **Cambiar precio** en masa: fija un monto o sube/baja un porcentaje a las variantes seleccionadas.
- Una variante con pedidos no se elimina: se desactiva.

## Inventario
- **Controlar stock:** si está encendido, la tienda no vende más de lo disponible. Apagado = se vende sin límite.
- **Permitir sobreventa:** se puede comprar sin stock (venta por encargo). La tienda lo muestra como "Disponible por encargo".
- **Stock mínimo:** bajo este número el producto aparece en la alerta *Stock bajo*.
- **Stock inicial** (solo al crear): queda registrado como *Entrada manual* en la bodega elegida.
- Al editar se ve el stock por bodega. Para cambiarlo usa **Registrar movimiento** (arriba) o *Inventario → Existencias*: el stock nunca se escribe directo, siempre deja un movimiento.
- Con variantes, cada una tiene su propio stock: en la pestaña *Variantes*, botón **Inventario** (Movimiento, Conteo, Configurar).
- La lista de productos tiene la columna **Stock** (unidades disponibles en bodegas activas) y el filtro **Disponibilidad**.

## Imágenes
- Sube varias a la vez (JPG, PNG, WebP o GIF, hasta 10 MB). Se reducen a 2400 px, se corrige la orientación y se genera una miniatura.
- Arrastra para ordenar. La marcada como **Principal** es la que aparece en el catálogo.
- Escribe siempre el **texto alternativo**: ayuda a Google y a personas con lectores de pantalla.
- Puedes asociar una imagen a una variante (ej.: la foto de la polera blanca a la variante Blanco).

## Acciones masivas
Selecciona productos en el listado para **publicar**, **despublicar** o **archivar** varios a la vez. Al terminar se informa cuáles no se pudieron cambiar y por qué.

## Importar y exportar
- **Importar / exportar → Exportar a Excel o CSV** descarga los productos que coinciden con la búsqueda y los filtros.
- El archivo exportado tiene el mismo formato que la plantilla: puedes editarlo en Excel y volver a importarlo para actualizar precios o datos. Ver el manual de *Importar productos*.

## Ejemplos
- Polera en 3 tallas y 2 colores: crea el producto con precio base, luego *Variantes → Generar combinaciones*.
- Liquidación de verano: filtra por categoría, exporta, cambia la columna `precio_oferta` y vuelve a importar.

## Errores frecuentes
- *"No tiene ninguna variante activa con precio":* revisa la pestaña Variantes o el precio de venta.
- *"El SKU ya lo usa…":* cada SKU es único en toda la tienda.
- *El producto no aparece en la tienda:* revisa que esté *Publicado* y que la fecha de publicación no sea futura.

## Recomendaciones
- Usa SKU cortos y legibles (`POL-ORG-NEG-M`).
- Completa costo para ver el margen real.
- Productos sin imágenes venden mucho menos: usa el filtro *Sin imágenes* para encontrarlos.

## Advertencias
- Cambiar la URL amigable de un producto publicado rompe los enlaces compartidos.
- El costo es confidencial: solo lo ven roles con acceso al catálogo.

## Relaciones con otros módulos
- **Categorías, marcas y atributos** organizan y filtran el catálogo.
- **Inventario** lleva el stock de cada variante por bodega (ver *Inventario → Existencias*).
- **Pedidos** guardan copia del nombre y precio al momento de la compra.
