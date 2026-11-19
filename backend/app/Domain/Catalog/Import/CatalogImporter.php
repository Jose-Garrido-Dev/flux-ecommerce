<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\ProductPresenter;
use App\Domain\Catalog\Services\ProductWriter;
use App\Domain\Pricing\Models\TaxClass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Importación masiva de productos desde CSV o Excel (módulo 2.4).
 *
 * 1. analyze(): lee el archivo con el mapeo de columnas, valida cada fila y
 *    calcula cuántos productos se crean y cuántos se actualizan, sin escribir nada.
 * 2. run(): (en la cola) vuelve a validar y escribe producto por producto, cada
 *    uno en su propia transacción: un error en una fila no afecta a las demás.
 *
 * Las celdas vacías NO borran datos existentes al actualizar.
 */
class CatalogImporter
{
    public const MAX_ROWS = 20000;

    public const MAX_STORED_ERRORS = 5000;

    private const STATUS_MAP = [
        'borrador' => 'draft', 'draft' => 'draft',
        'publicado' => 'active', 'activo' => 'active', 'active' => 'active', 'published' => 'active',
        'archivado' => 'archived', 'archived' => 'archived',
    ];

    /** Cachés por ejecución para no consultar la base en cada fila */
    private array $brands = [];

    private array $categories = [];

    private array $attributes = [];

    private array $values = [];

    private array $taxes = [];

    public function __construct(private readonly ProductWriter $writer) {}

    /** Lee solo los encabezados del archivo. */
    public function headers(CatalogImport $import): array
    {
        foreach (SpreadsheetReader::rows($this->absolutePath($import), $import->extension()) as $cells) {
            return array_values(array_filter($cells, fn ($h) => $h !== ''));
        }

        return [];
    }

    /** Análisis previo: valida todo y resume lo que pasará. No escribe en el catálogo. */
    public function analyze(CatalogImport $import): CatalogImport
    {
        $parsed = $this->parse($import);
        $errors = $parsed['errors'];

        $skus = array_keys($parsed['rows']);
        $existing = [];
        foreach (array_chunk($skus, 1000) as $chunk) {
            $existing += ProductVariant::query()->whereIn('sku', $chunk)->pluck('id', 'sku')->all();
        }

        $create = $update = 0;
        foreach ($parsed['rows'] as $sku => $row) {
            $exists = isset($existing[$sku]);
            if ($import->mode === 'create' && $exists) {
                $errors[] = $this->error($row['_row'], $sku, 'El SKU ya existe y el modo es "solo crear".');
            } elseif ($import->mode === 'update' && ! $exists) {
                $errors[] = $this->error($row['_row'], $sku, 'El SKU no existe y el modo es "solo actualizar".');
            } else {
                $exists ? $update++ : $create++;
            }
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);
        $failedRows = count(array_unique(array_column($errors, 'row')));

        $import->update([
            'status' => 'analyzed',
            'mapping' => $import->mapping,
            'total_rows' => $parsed['total'],
            'summary' => [
                'headers' => $parsed['headers'],
                'products' => count($parsed['groups']),
                'variants_to_create' => $create,
                'variants_to_update' => $update,
                'rows_with_errors' => $failedRows,
                'missing_required' => $parsed['missing_required'],
                'preview' => array_slice(array_map(fn ($r) => array_diff_key($r, ['_row' => 1]), array_values($parsed['rows'])), 0, 10),
            ],
            'errors' => array_slice($errors, 0, self::MAX_STORED_ERRORS),
            'failed_count' => $failedRows,
        ]);

        return $import;
    }

    /** Importación real (se ejecuta en la cola). */
    public function run(CatalogImport $import): void
    {
        $import->update(['status' => 'processing', 'started_at' => now(), 'processed_rows' => 0,
            'created_count' => 0, 'updated_count' => 0, 'failed_count' => 0, 'failure' => null]);

        $parsed = $this->parse($import);
        $errors = $parsed['errors'];
        $failedRows = array_fill_keys(array_column($errors, 'row'), true);
        $created = $updated = 0;
        $processed = count($failedRows);

        foreach ($parsed['groups'] as $slug => $skus) {
            $rows = array_map(fn ($sku) => $parsed['rows'][$sku], $skus);

            try {
                [$c, $u] = DB::transaction(fn () => $this->importGroup($import, $slug, $rows));
                $created += $c;
                $updated += $u;
            } catch (ValidationException $e) {
                foreach ($rows as $row) {
                    $errors[] = $this->error($row['_row'], $row['sku'], collect($e->errors())->flatten()->implode(' '));
                    $failedRows[$row['_row']] = true;
                }
                $this->forgetCaches();
            } catch (Throwable $e) {
                report($e);
                foreach ($rows as $row) {
                    $errors[] = $this->error($row['_row'], $row['sku'], 'Error inesperado al guardar: '.Str::limit($e->getMessage(), 200));
                    $failedRows[$row['_row']] = true;
                }
                $this->forgetCaches(); // lo creado dentro de la transacción revertida ya no existe
            }

            $processed += count($rows);
            if ($processed % 100 < count($rows)) {
                $import->update(['processed_rows' => $processed, 'created_count' => $created, 'updated_count' => $updated]);
            }
        }

        usort($errors, fn ($a, $b) => $a['row'] <=> $b['row']);

        $import->update([
            'status' => 'done',
            'processed_rows' => $parsed['total'],
            'created_count' => $created,
            'updated_count' => $updated,
            'failed_count' => count($failedRows),
            'errors' => array_slice($errors, 0, self::MAX_STORED_ERRORS),
            'finished_at' => now(),
        ]);
    }

    /** Informe de errores descargable (CSV que abre bien en Excel). */
    public function errorReportCsv(CatalogImport $import): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['fila', 'sku', 'error'], ';');
        foreach ($import->errors ?? [] as $e) {
            fputcsv($out, [$e['row'], $e['sku'], $e['message']], ';');
        }
        rewind($out);

        return stream_get_contents($out);
    }

    // ------------------------------------------------------------------

    /**
     * @return array{total: int, headers: list<string>, rows: array<string, array>, groups: array<string, list<string>>, errors: list<array>, missing_required: list<string>}
     */
    private function parse(CatalogImport $import): array
    {
        $headers = [];
        $mapping = $import->mapping ?? [];
        $rows = [];
        $groups = [];
        $errors = [];
        $total = 0;
        $barcodes = [];

        foreach (SpreadsheetReader::rows($this->absolutePath($import), $import->extension()) as $n => $cells) {
            if ($n === 1) {
                $headers = $cells;
                if ($mapping === []) {
                    $mapping = ImportColumns::guessMapping($headers);
                    $import->mapping = $mapping;
                }

                continue;
            }
            if (implode('', $cells) === '') {
                continue; // fila vacía
            }
            $total++;
            if ($total > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => 'El archivo tiene más de '.self::MAX_ROWS.' filas. Divídelo en partes.']);
            }

            $raw = [];
            foreach ($headers as $i => $header) {
                $field = $mapping[$header] ?? null;
                if ($field && ! isset($raw[$field])) {
                    $raw[$field] = $cells[$i] ?? '';
                }
            }

            [$row, $rowErrors] = $this->normalizeRow($raw);
            $row['_row'] = $n;
            $sku = $row['sku'];

            if ($sku !== '' && isset($rows[$sku])) {
                $rowErrors[] = "SKU repetido en el archivo (también está en la fila {$rows[$sku]['_row']}).";
            }
            if ($row['barcode'] !== null) {
                if (isset($barcodes[$row['barcode']])) {
                    $rowErrors[] = "Código de barras repetido en el archivo (fila {$barcodes[$row['barcode']]}).";
                }
                $barcodes[$row['barcode']] ??= $n;
            }

            if ($rowErrors !== []) {
                foreach ($rowErrors as $message) {
                    $errors[] = $this->error($n, $sku, $message);
                }

                continue;
            }

            $rows[$sku] = $row;
            $groups[$row['slug']][] = $sku;
        }

        // Las variantes de un mismo producto deben tener combinaciones de opciones distintas
        foreach ($groups as $slug => $skus) {
            $seen = [];
            foreach ($skus as $sku) {
                $key = json_encode($rows[$sku]['options']);
                if ($rows[$sku]['options'] !== [] && isset($seen[$key])) {
                    $errors[] = $this->error($rows[$sku]['_row'], $sku, "Repite las mismas opciones que el SKU {$seen[$key]} del mismo producto.");
                    unset($rows[$sku]);
                    $groups[$slug] = array_values(array_diff($groups[$slug], [$sku]));
                }
                $seen[$key] ??= $sku;
            }
            if (count($skus) > 1 && collect($skus)->contains(fn ($s) => isset($rows[$s]) && $rows[$s]['options'] === [])) {
                foreach ($skus as $sku) {
                    if (isset($rows[$sku])) {
                        $errors[] = $this->error($rows[$sku]['_row'], $sku, "El producto \"$slug\" tiene varias filas: cada una necesita sus opciones (por ejemplo Color y Talla).");
                        unset($rows[$sku]);
                    }
                }
                unset($groups[$slug]);
            }
        }
        $groups = array_filter($groups);

        $mapped = array_filter($mapping);
        $missing = array_values(array_diff(ImportColumns::required(), $mapped));

        return compact('total', 'headers', 'rows', 'groups', 'errors') + ['missing_required' => $missing];
    }

    /** @return array{0: array, 1: list<string>} fila normalizada y errores */
    private function normalizeRow(array $raw): array
    {
        $errors = [];
        $get = fn (string $f) => trim((string) ($raw[$f] ?? ''));

        $sku = Str::upper($get('sku'));
        $name = $get('name');
        if ($sku === '') {
            $errors[] = 'Falta el SKU.';
        } elseif (strlen($sku) > 64 || ! preg_match('/^[A-Z0-9][A-Z0-9._\-\/]*$/', $sku)) {
            $errors[] = "SKU \"$sku\" no válido: usa letras, números, guion, punto o barra (máx. 64).";
        }
        if ($name === '') {
            $errors[] = 'Falta el nombre.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'El nombre supera 255 caracteres.';
        }

        $money = function (string $field, string $label) use ($get, &$errors): ?int {
            $v = $get($field);
            if ($v === '') {
                return null;
            }
            $clean = str_replace(['$', '.', ' ', "\u{00A0}"], '', $v);
            if (! preg_match('/^\d+$/', $clean)) {
                $errors[] = "$label \"$v\" no es un monto válido (usa solo números, sin decimales).";

                return null;
            }

            return (int) $clean;
        };
        $int = function (string $field, string $label) use ($get, &$errors): ?int {
            $v = $get($field);
            if ($v === '') {
                return null;
            }
            if (! preg_match('/^\d+$/', $v)) {
                $errors[] = "$label \"$v\" debe ser un número entero positivo.";

                return null;
            }

            return (int) $v;
        };
        $date = function (string $field, string $label) use ($get, &$errors): ?string {
            $v = $get($field);
            if ($v === '') {
                return null;
            }
            foreach (['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d', 'd-m-Y H:i', 'd-m-Y', 'd/m/Y H:i', 'd/m/Y'] as $format) {
                try {
                    $d = CarbonImmutable::createFromFormat($format, $v, config('app.timezone'));
                } catch (Throwable) {
                    $d = false;
                }
                if ($d !== false && $d->format($format) === $v) {
                    return (str_contains($format, 'H') ? $d : $d->startOfDay())->toDateTimeString();
                }
            }
            $errors[] = "$label \"$v\" no es una fecha válida (usa 2026-12-31 23:59).";

            return null;
        };

        $price = $money('price', 'Precio');
        if ($get('price') === '') {
            $errors[] = 'Falta el precio.';
        }
        $compare = $money('compare_at_price', 'Precio anterior');
        $sale = $money('sale_price', 'Precio de oferta');
        $cost = $money('cost', 'Costo');
        $saleStarts = $date('sale_starts_at', 'Oferta desde');
        $saleEnds = $date('sale_ends_at', 'Oferta hasta');

        if ($price !== null) {
            if ($price === 0) {
                $errors[] = 'El precio debe ser mayor que 0.';
            }
            if ($compare !== null && $compare <= $price) {
                $errors[] = 'El precio anterior debe ser mayor que el precio.';
            }
            if ($sale !== null && $sale >= $price) {
                $errors[] = 'El precio de oferta debe ser menor que el precio.';
            }
        }
        if ($saleStarts && $saleEnds && $saleEnds <= $saleStarts) {
            $errors[] = 'La oferta debe terminar después de comenzar.';
        }

        $status = null;
        if ($get('status') !== '') {
            $status = self::STATUS_MAP[Str::lower(Str::ascii($get('status')))] ?? null;
            if (! $status) {
                $errors[] = "Estado \"{$get('status')}\" no válido: usa borrador, publicado o archivado.";
            }
        }
        $featured = null;
        if ($get('featured') !== '') {
            $f = Str::lower(Str::ascii($get('featured')));
            $featured = in_array($f, ['si', 's', '1', 'true', 'yes', 'x'], true) ? true
                : (in_array($f, ['no', 'n', '0', 'false'], true) ? false : null);
            if ($featured === null) {
                $errors[] = "Destacado \"{$get('featured')}\" no válido: usa si o no.";
            }
        }
        $tax = null;
        if ($get('tax') !== '') {
            $tax = Str::lower(Str::ascii($get('tax')));
            if (! in_array($tax, ['iva', 'exento'], true)) {
                $errors[] = "Impuesto \"{$get('tax')}\" no válido: usa iva o exento.";
            }
        }

        $options = [];
        for ($i = 1; $i <= 3; $i++) {
            $optName = $get("option{$i}_name");
            $optValue = $get("option{$i}_value");
            if ($optName === '' && $optValue === '') {
                continue;
            }
            if ($optName === '' || $optValue === '') {
                $errors[] = "La opción $i necesita nombre y valor.";

                continue;
            }
            $key = Str::lower(Str::ascii($optName));
            if (isset($options[$key])) {
                $errors[] = "La opción \"$optName\" está repetida en la fila.";
            }
            $options[$key] = ['name' => $optName, 'value' => $optValue];
        }
        ksort($options);

        foreach (['seo_title' => 70, 'seo_description' => 160, 'short_description' => 500] as $f => $max) {
            if (mb_strlen($get($f)) > $max) {
                $errors[] = ImportColumns::label($f)." supera $max caracteres.";
            }
        }

        $category = $get('category');
        if ($category !== '' && count($this->splitPath($category)) > Category::MAX_DEPTH) {
            $errors[] = 'La categoría tiene más de '.Category::MAX_DEPTH.' niveles.';
        }

        $slug = Str::slug($get('slug') ?: $name);
        if ($slug === '' && $name !== '') {
            $errors[] = 'No se pudo formar la URL amigable desde el nombre.';
        }

        $row = [
            'sku' => $sku,
            'name' => $name,
            'slug' => Str::limit($slug, 180, ''),
            'short_description' => $get('short_description') ?: null,
            'description' => $get('description') ?: null,
            'brand' => $get('brand') ?: null,
            'category' => $category ?: null,
            'categories' => array_values(array_filter(array_map('trim', explode('|', $get('categories'))))),
            'tags' => array_values(array_filter(array_map('trim', explode(',', $get('tags'))))),
            'status' => $status,
            'featured' => $featured,
            'tax' => $tax,
            'price' => $price,
            'compare_at_price' => $compare,
            'sale_price' => $sale,
            'sale_starts_at' => $saleStarts,
            'sale_ends_at' => $saleEnds,
            'cost' => $cost,
            'barcode' => $get('barcode') ?: null,
            'weight_g' => $int('weight_g', 'Peso'),
            'length_mm' => $int('length_mm', 'Largo'),
            'width_mm' => $int('width_mm', 'Ancho'),
            'height_mm' => $int('height_mm', 'Alto'),
            'options' => $options,
            'seo_title' => $get('seo_title') ?: null,
            'seo_description' => $get('seo_description') ?: null,
        ];

        return [$row, $errors];
    }

    /** @return array{0: int, 1: int} variantes creadas y actualizadas */
    private function importGroup(CatalogImport $import, string $slug, array $rows): array
    {
        $first = $rows[0];
        $existingVariants = ProductVariant::query()->whereIn('sku', array_column($rows, 'sku'))->get()->keyBy('sku');

        foreach ($rows as $row) {
            $exists = $existingVariants->has($row['sku']);
            if ($import->mode === 'create' && $exists) {
                throw ValidationException::withMessages(['sku' => "El SKU {$row['sku']} ya existe y el modo es \"solo crear\"."]);
            }
            if ($import->mode === 'update' && ! $exists) {
                throw ValidationException::withMessages(['sku' => "El SKU {$row['sku']} no existe y el modo es \"solo actualizar\"."]);
            }
        }

        $productIds = $existingVariants->pluck('product_id')->unique();
        if ($productIds->count() > 1) {
            throw ValidationException::withMessages(['sku' => 'Estas filas comparten slug pero sus SKU pertenecen a productos distintos.']);
        }

        $product = $productIds->isNotEmpty()
            ? Product::query()->findOrFail($productIds->first())
            : ($import->mode !== 'create' ? Product::query()->where('slug', $slug)->first() : null);
        $isNew = ! $product;

        $product ??= new Product([
            'status' => ProductStatus::Draft,
            'slug' => $this->writer->uniqueSlug($slug),
        ]);

        // Datos del producto: de la primera fila del grupo; las celdas vacías no borran nada
        $attrs = array_filter([
            'name' => $first['name'],
            'short_description' => $first['short_description'],
            'description' => $first['description'] !== null ? app(ProductPresenter::class)->sanitize($first['description']) : null,
            'seo_title' => $first['seo_title'],
            'seo_description' => $first['seo_description'],
            'featured' => $first['featured'],
            'tags' => $first['tags'] ?: null,
        ], fn ($v) => $v !== null);
        $product->fill($attrs);

        if ($first['brand']) {
            $product->brand_id = $this->brandId($first['brand'], $import->create_missing);
        }
        if ($first['category']) {
            $product->primary_category_id = $this->categoryId($first['category'], $import->create_missing);
        }
        if ($first['tax'] || $isNew) {
            $product->tax_class_id = $this->taxId($first['tax'] ?? 'iva');
        }
        $product->save();

        $categoryIds = array_filter(array_merge(
            [$product->primary_category_id],
            array_map(fn ($path) => $this->categoryId($path, $import->create_missing), $first['categories']),
        ));
        if ($categoryIds !== []) {
            $product->categories()->syncWithoutDetaching(array_unique($categoryIds));
        }

        $created = $updated = 0;
        $position = (int) $product->variants()->max('position');
        foreach ($rows as $i => $row) {
            $variant = $existingVariants->get($row['sku']);
            $isNewVariant = ! $variant;
            $variant ??= new ProductVariant([
                'product_id' => $product->id,
                'position' => $isNew ? $i : ++$position,
                'is_default' => $isNew && $i === 0,
            ]);

            $currentPrice = $variant->exists ? $variant->prices()->where('currency', 'CLP')->first() : null;
            $data = array_filter([
                'sku' => $row['sku'],
                'barcode' => $row['barcode'],
                'weight_g' => $row['weight_g'],
                'length_mm' => $row['length_mm'],
                'width_mm' => $row['width_mm'],
                'height_mm' => $row['height_mm'],
            ], fn ($v) => $v !== null);

            $priceTouched = $row['price'] !== null || $row['compare_at_price'] !== null || $row['sale_price'] !== null || $row['cost'] !== null;
            if ($priceTouched || $isNewVariant) {
                $data += [
                    'price_amount' => $row['price'] ?? $currentPrice?->price_amount,
                    'compare_at_amount' => $row['compare_at_price'] ?? $currentPrice?->compare_at_amount,
                    'cost_amount' => $row['cost'] ?? $currentPrice?->cost_amount,
                    'sale_amount' => $row['sale_price'] ?? $currentPrice?->sale_amount,
                    'sale_starts_at' => $row['sale_starts_at'] ?? $currentPrice?->sale_starts_at,
                    'sale_ends_at' => $row['sale_ends_at'] ?? $currentPrice?->sale_ends_at,
                ];
            }

            $this->writer->saveVariant($variant, $data);
            $isNewVariant ? $created++ : $updated++;

            if ($row['options'] !== []) {
                $sync = [];
                foreach ($row['options'] as $option) {
                    [$attributeId, $valueId] = $this->optionIds($option['name'], $option['value'], $import->create_missing);
                    $sync[$valueId] = ['attribute_id' => $attributeId];
                }
                $variant->attributeValues()->sync($sync);
            }
        }

        $this->writer->syncHasVariants($product);

        if ($first['status'] === 'active') {
            $this->writer->publish($product->fresh());
        } elseif ($first['status'] !== null) {
            $product->update(['status' => ProductStatus::from($first['status'])]);
        }

        return [$created, $updated];
    }

    private function brandId(string $name, bool $create): int
    {
        $key = Brand::normalize($name);
        if (! isset($this->brands[$key])) {
            $brand = Brand::query()->where('normalized_name', $key)->first();
            if (! $brand) {
                if (! $create) {
                    throw ValidationException::withMessages(['brand' => "La marca \"$name\" no existe."]);
                }
                $brand = Brand::query()->create(['name' => $name, 'slug' => $this->uniqueSlug('brands', Str::slug($name))]);
            }
            $this->brands[$key] = $brand->id;
        }

        return $this->brands[$key];
    }

    private function categoryId(string $path, bool $create): int
    {
        $names = $this->splitPath($path);
        $key = Str::lower(Str::ascii(implode('>', $names)));
        if (isset($this->categories[$key])) {
            return $this->categories[$key];
        }

        $parent = null;
        foreach ($names as $name) {
            $category = Category::query()
                ->where('parent_id', $parent?->id)
                ->whereRaw('lower(name) = ?', [Str::lower($name)])
                ->first();
            if (! $category) {
                if (! $create) {
                    throw ValidationException::withMessages(['category' => "La categoría \"$path\" no existe."]);
                }
                $base = Str::slug($name);
                $slug = Category::query()->where('slug', $base)->exists() && $parent ? Str::slug($parent->name.' '.$name) : $base;
                try {
                    $category = Category::query()->create([
                        'name' => $name,
                        'slug' => $this->uniqueSlug('categories', $slug),
                        'parent_id' => $parent?->id,
                    ]);
                } catch (InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['category' => $e->getMessage()]);
                }
            }
            $parent = $category;
        }

        return $this->categories[$key] = $parent->id;
    }

    /** @return array{0: int, 1: int} attribute_id, attribute_value_id */
    private function optionIds(string $name, string $label, bool $create): array
    {
        $attrKey = Str::lower(Str::ascii($name));
        if (! isset($this->attributes[$attrKey])) {
            $attribute = Attribute::query()->where('code', Str::slug($name, '_'))
                ->orWhereRaw('lower(name) = ?', [Str::lower($name)])->first();
            if (! $attribute) {
                if (! $create) {
                    throw ValidationException::withMessages(['option' => "El atributo \"$name\" no existe."]);
                }
                $isColor = in_array($attrKey, ['color', 'colour'], true);
                $attribute = Attribute::query()->create([
                    'code' => Str::slug($name, '_'),
                    'name' => $name,
                    'type' => $isColor ? 'color' : 'select',
                    'display' => $isColor ? 'swatch' : 'pill',
                    'is_variant_axis' => true,
                    'is_filterable' => true,
                    'position' => (int) Attribute::query()->max('position') + 1,
                ]);
            } elseif (! $attribute->is_variant_axis) {
                $attribute->update(['is_variant_axis' => true]);
            }
            $this->attributes[$attrKey] = $attribute->id;
        }
        $attributeId = $this->attributes[$attrKey];

        $valueKey = $attributeId.':'.Str::slug($label);
        if (! isset($this->values[$valueKey])) {
            $value = AttributeValue::query()->where('attribute_id', $attributeId)->where('value', Str::slug($label))->first();
            if (! $value) {
                if (! $create) {
                    throw ValidationException::withMessages(['option' => "El valor \"$label\" de \"$name\" no existe."]);
                }
                $value = AttributeValue::query()->create([
                    'attribute_id' => $attributeId,
                    'label' => $label,
                    'value' => $label,
                    'position' => (int) AttributeValue::query()->where('attribute_id', $attributeId)->max('position') + 1,
                ]);
            }
            $this->values[$valueKey] = $value->id;
        }

        return [$attributeId, $this->values[$valueKey]];
    }

    private function taxId(string $code): int
    {
        return $this->taxes[$code] ??= TaxClass::query()
            ->where($code === 'exento' ? 'is_exempt' : 'is_default', true)
            ->value('id') ?? throw ValidationException::withMessages(['tax' => 'No hay clase de impuesto configurada. Ejecuta los datos base (db:seed).']);
    }

    private function uniqueSlug(string $table, string $base): string
    {
        $base = $base ?: 'item';
        $slug = $base;
        for ($i = 2; DB::table($table)->where('slug', $slug)->exists(); $i++) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    /** "Ropa > Poleras" => ["Ropa", "Poleras"] (también acepta / como separador) */
    private function splitPath(string $path): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\s*[>\/]\s*/', $path))));
    }

    private function forgetCaches(): void
    {
        $this->brands = $this->categories = $this->attributes = $this->values = [];
    }

    private function error(int $row, string $sku, string $message): array
    {
        return ['row' => $row, 'sku' => $sku, 'message' => $message];
    }

    private function absolutePath(CatalogImport $import): string
    {
        return Storage::disk('local')->path($import->path);
    }
}
