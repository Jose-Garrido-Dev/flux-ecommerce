<?php

namespace App\Domain\Catalog\Import;

use Illuminate\Support\Str;

/**
 * Columnas de la plantilla de importación/exportación de productos.
 * Una fila = una variante. Las filas con el mismo "slug" (o el mismo nombre si
 * no hay slug) forman un solo producto con varias variantes.
 */
final class ImportColumns
{
    /** campo => [encabezado de la plantilla, descripción, obligatorio] */
    public const FIELDS = [
        'sku' => ['sku', 'Código único de la variante. Identifica qué actualizar.', true],
        'name' => ['nombre', 'Nombre del producto.', true],
        'slug' => ['slug', 'URL amigable. Filas con el mismo slug son variantes del mismo producto. Si queda vacío se arma desde el nombre.', false],
        'short_description' => ['descripcion_corta', 'Hasta 500 caracteres.', false],
        'description' => ['descripcion', 'Texto o HTML simple (p, ul, li, strong).', false],
        'brand' => ['marca', 'Nombre de la marca.', false],
        'category' => ['categoria', 'Categoría principal con su ruta: Ropa > Poleras', false],
        'categories' => ['categorias_secundarias', 'Otras categorías separadas por |, por ejemplo: Ofertas | Hogar > Tazas', false],
        'tags' => ['etiquetas', 'Separadas por coma.', false],
        'status' => ['estado', 'borrador, publicado o archivado. Vacío = borrador al crear.', false],
        'featured' => ['destacado', 'si o no.', false],
        'price' => ['precio', 'Precio de venta en pesos, con IVA incluido, sin puntos ni signo $. Ej.: 12990', true],
        'compare_at_price' => ['precio_anterior', 'Precio "antes" tachado. Debe ser mayor que el precio.', false],
        'sale_price' => ['precio_oferta', 'Precio de oferta. Debe ser menor que el precio.', false],
        'sale_starts_at' => ['oferta_desde', 'Fecha y hora: 2026-12-01 00:00', false],
        'sale_ends_at' => ['oferta_hasta', 'Fecha y hora: 2026-12-24 23:59', false],
        'cost' => ['costo', 'Costo interno. Nunca se muestra en la tienda.', false],
        'tax' => ['impuesto', 'iva (19%) o exento. Vacío = iva.', false],
        'barcode' => ['codigo_barras', 'EAN/UPC. Debe ser único.', false],
        'weight_g' => ['peso_g', 'Peso en gramos (para el despacho).', false],
        'length_mm' => ['largo_mm', 'Largo en milímetros.', false],
        'width_mm' => ['ancho_mm', 'Ancho en milímetros.', false],
        'height_mm' => ['alto_mm', 'Alto en milímetros.', false],
        'stock' => ['stock', 'Unidades físicas en la bodega principal. Al actualizar, la diferencia se registra como ajuste de inventario.', false],
        'min_stock' => ['stock_minimo', 'Bajo esta cantidad se alerta stock bajo.', false],
        'track_stock' => ['controlar_stock', 'si o no. "no" = se vende sin límite de unidades. Vacío = si al crear.', false],
        'option1_name' => ['opcion1_nombre', 'Atributo de variante, por ejemplo Color.', false],
        'option1_value' => ['opcion1_valor', 'Valor, por ejemplo Negro.', false],
        'option2_name' => ['opcion2_nombre', 'Segundo atributo, por ejemplo Talla.', false],
        'option2_value' => ['opcion2_valor', 'Valor, por ejemplo M.', false],
        'option3_name' => ['opcion3_nombre', 'Tercer atributo (opcional).', false],
        'option3_value' => ['opcion3_valor', 'Valor del tercer atributo.', false],
        'seo_title' => ['meta_titulo', 'Título para buscadores (hasta 70 caracteres).', false],
        'seo_description' => ['meta_descripcion', 'Descripción para buscadores (hasta 160 caracteres).', false],
    ];

    /** Otros nombres de columna que se reconocen automáticamente. */
    private const ALIASES = [
        'sku' => ['codigo', 'code', 'referencia', 'ref'],
        'name' => ['name', 'producto', 'titulo', 'title'],
        'short_description' => ['resumen', 'short_description'],
        'description' => ['description', 'detalle'],
        'brand' => ['brand', 'fabricante'],
        'category' => ['category', 'categoria_principal'],
        'price' => ['price', 'precio_venta', 'valor'],
        'compare_at_price' => ['precio_antes', 'compare_at_price'],
        'sale_price' => ['oferta', 'precio_promocional', 'sale_price'],
        'cost' => ['precio_costo', 'cost'],
        'barcode' => ['ean', 'upc', 'barcode', 'codigo_de_barras'],
        'weight_g' => ['peso', 'peso_gramos', 'weight'],
        'stock' => ['existencias', 'cantidad', 'inventario', 'unidades'],
        'status' => ['status'],
        'tags' => ['tags'],
    ];

    /** @return list<string> */
    public static function headers(): array
    {
        return array_column(self::FIELDS, 0);
    }

    public static function label(string $field): string
    {
        return self::FIELDS[$field][0] ?? $field;
    }

    /** @return list<string> */
    public static function required(): array
    {
        return array_keys(array_filter(self::FIELDS, fn ($f) => $f[2]));
    }

    /** Propone un mapeo encabezado del archivo => campo. Los no reconocidos quedan en null (se ignoran). */
    public static function guessMapping(array $headers): array
    {
        $lookup = [];
        foreach (self::FIELDS as $field => [$header]) {
            $lookup[$header] = $field;
            $lookup[$field] = $field;
        }
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $lookup[$alias] ??= $field;
            }
        }

        $mapping = [];
        $used = [];
        foreach ($headers as $header) {
            $key = Str::snake(Str::ascii(Str::lower(trim((string) $header))));
            $key = trim(preg_replace('/[^a-z0-9]+/', '_', $key), '_');
            $field = $lookup[$key] ?? null;
            if ($field && ! isset($used[$field])) {
                $mapping[$header] = $field;
                $used[$field] = true;
            } else {
                $mapping[$header] = null;
            }
        }

        return $mapping;
    }

    /** @return array<string, string> campo => "encabezado — descripción" para selectores */
    public static function options(): array
    {
        return collect(self::FIELDS)->mapWithKeys(fn ($f, $field) => [$field => $f[0].($f[2] ? ' (obligatorio)' : '')])->all();
    }
}
