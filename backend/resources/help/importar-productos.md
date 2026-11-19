# Importar productos

**Para qué sirve:** cargar o actualizar muchos productos de una vez desde Excel (.xlsx) o CSV.

## Pasos
1. Descarga la **Plantilla Excel** (incluye una hoja de instrucciones) o exporta tus productos actuales desde el listado.
2. Completa una fila por variante. Columnas obligatorias: `sku`, `nombre`, `precio`.
3. Sube el archivo y elige qué hacer:
   - *Crear nuevos y actualizar existentes* (recomendado): busca cada fila por SKU.
   - *Solo crear*: los SKU que ya existen se informan como error.
   - *Solo actualizar*: los SKU nuevos se informan como error.
4. **Analizar archivo:** revisa el resumen, la vista previa y los errores. Hasta aquí no se cambia nada.
5. Si alguna columna quedó mal asignada, corrígela en *Columnas del archivo* y vuelve a analizar.
6. **Importar:** se ejecuta en segundo plano. Puedes salir de la página; al volver verás el avance y el resultado.
7. Descarga el **CSV de errores**, corrige esas filas y vuelve a importarlas.

## Formato
| Columna | Ejemplo |
| --- | --- |
| sku | `POL-NEG-M` |
| nombre | `Polera algodón` |
| slug | `polera-algodon` (repítelo en todas las filas de las variantes de un mismo producto) |
| precio, precio_anterior, precio_oferta, costo | `9990` o `$9.990` |
| oferta_desde / oferta_hasta | `2026-12-01` o `01-12-2026 09:00` |
| marca | `Nike` |
| categoria | `Ropa > Poleras` |
| categorias_secundarias | `Ofertas | Verano` |
| estado | `borrador`, `publicado` o `archivado` |
| destacado | `si` o `no` |
| impuesto | `iva` o `exento` |
| opcion1_nombre / opcion1_valor | `Color` / `Negro` (hasta 3 opciones) |

## Reglas
- Las **celdas vacías no borran** datos existentes: puedes subir solo `sku` y `precio` para cambiar precios.
- Un SKU o código de barras repetido en el archivo se informa con el número de fila.
- Los archivos de Excel en español (separados por punto y coma) y los guardados como "CSV (Windows)" se leen bien.
- Máximo 20.000 filas y 20 MB por archivo.
- Si activas *Crear marcas, categorías y opciones que no existan*, se crean automáticamente; si no, esas filas quedan con error.

## Errores frecuentes
- *"no es un monto válido":* el precio tiene letras o decimales.
- *"Faltan columnas obligatorias":* asigna la columna en *Columnas del archivo*.
- *Se queda "En cola":* el proceso de colas del servidor no está corriendo. Revisa la sección "Colas" de la guía de instalación (`php artisan queue:work`).

## Advertencias
- Un producto importado como *publicado* aparece de inmediato en la tienda si tiene precio.
- Revisa la vista previa antes de confirmar: la importación no se deshace automáticamente (puedes exportar antes como respaldo).

## Relaciones con otros módulos
- **Productos**, **Marcas**, **Categorías** y **Atributos** reciben los datos.
- **Auditoría** registra los cambios de cada producto.
