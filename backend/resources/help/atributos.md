# Atributos

**Para qué sirve:** definir características de los productos: opciones de compra (Color, Talla) y especificaciones (Material, Capacidad).

## Campos
| Campo | Explicación |
| --- | --- |
| Nombre | Ej.: "Color" |
| Código | Identificador interno: `color`. Se genera solo |
| Tipo | Lista, color, texto, número, imagen o medida |
| Visualización | Cómo se muestra al elegir: botones, muestras de color, lista |
| Unidad | Para números y medidas: cm, ml, kg… |
| Se usa para variantes | El cliente lo elige al comprar (genera combinaciones) |
| Filtrable | Aparece como filtro en el catálogo |
| Obligatorio | Todo producto debe tenerlo |
| Valores | Lista de opciones. En tipo color agrega el código (#000000); en imagen, la imagen |

## Crear, modificar y desactivar
- **Nuevo atributo**, agrega sus valores y ordénalos arrastrando.
- Cambiar el nombre de un valor no afecta a los productos que lo usan.

## Ejemplos
- **Color** (tipo color, variantes, filtrable): Negro #111111, Blanco #ffffff.
- **Talla** (lista, variantes): S, M, L, XL.
- **Material** (texto, filtrable): se completa en la pestaña Especificaciones del producto.

## Recomendaciones
- Crea los atributos antes de cargar productos con variantes.
- Usa pocos atributos filtrables: demasiados filtros confunden.

## Relaciones con otros módulos
- **Productos → Variantes** usa los atributos marcados para variantes.
- **Importar productos** crea atributos y valores desde las columnas `opcion1_nombre` / `opcion1_valor`.
