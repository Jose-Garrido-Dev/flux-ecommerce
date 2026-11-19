<?php

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

/**
 * Exporta productos en el MISMO formato de la plantilla de importación:
 * se puede exportar, editar en Excel y volver a importar para actualizar.
 * También genera la plantilla vacía con una hoja de instrucciones.
 */
class CatalogExporter
{
    /** @var array<int, string> nombre completo por categoría, para no repetir consultas */
    private array $categoryNames = [];

    /** @param  Builder<Product>|null  $query */
    public function export(string $path, string $format, ?Builder $query = null): void
    {
        $writer = $this->writer($format);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(ImportColumns::headers(), (new Style)->setFontBold()));

        $query ??= Product::query();
        $query->with(['brand', 'primaryCategory', 'categories', 'taxClass', 'variants.prices', 'variants.attributeValues.attribute', 'variants.inventoryItem', 'variants.levels'])
            ->orderBy('id')
            ->chunk(200, function ($products) use ($writer) {
                foreach ($products as $product) {
                    foreach ($product->variants as $variant) {
                        $writer->addRow(Row::fromValues($this->row($product, $variant)));
                    }
                }
            });

        $writer->close();
    }

    public function template(string $path, string $format): void
    {
        $writer = $this->writer($format);
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(ImportColumns::headers(), (new Style)->setFontBold()));

        if ($writer instanceof XlsxWriter) {
            $writer->getCurrentSheet()->setName('Productos');
            $writer->addNewSheetAndMakeItCurrent()->setName('Instrucciones');
            $writer->addRow(Row::fromValues(['columna', 'obligatoria', 'qué poner'], (new Style)->setFontBold()));
            foreach (ImportColumns::FIELDS as [$header, $help, $required]) {
                $writer->addRow(Row::fromValues([$header, $required ? 'sí' : 'no', $help]));
            }
            $writer->addRow(Row::fromValues(['']));
            $writer->addRow(Row::fromValues(['Una fila por variante. Para un producto con tallas, repite el mismo slug en cada fila y cambia opcion1_valor.']));
            $writer->addRow(Row::fromValues(['Al actualizar, las celdas vacías no borran el dato que ya existe.']));
        }

        $writer->close();
    }

    private function row(Product $product, ProductVariant $variant): array
    {
        $price = $variant->priceIn('CLP');
        $options = $variant->attributeValues->sortBy(fn ($v) => $v->attribute->position)->values();
        $secondary = $product->categories->where('id', '!=', $product->primary_category_id)
            ->map(fn ($c) => $this->categoryName($c))->implode(' | ');

        $values = [
            'sku' => $variant->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'brand' => $product->brand?->name,
            'category' => $product->primaryCategory ? $this->categoryName($product->primaryCategory) : null,
            'categories' => $secondary,
            'tags' => implode(', ', $product->tags ?? []),
            'status' => ['draft' => 'borrador', 'active' => 'publicado', 'archived' => 'archivado'][$product->status->value],
            'featured' => $product->featured ? 'si' : 'no',
            'price' => $price?->price_amount,
            'compare_at_price' => $price?->compare_at_amount,
            'sale_price' => $price?->sale_amount,
            'sale_starts_at' => $price?->sale_starts_at?->format('Y-m-d H:i'),
            'sale_ends_at' => $price?->sale_ends_at?->format('Y-m-d H:i'),
            'cost' => $price?->cost_amount,
            'tax' => $product->taxClass?->is_exempt ? 'exento' : 'iva',
            'barcode' => $variant->barcode,
            'weight_g' => $variant->weight_g,
            'length_mm' => $variant->length_mm,
            'width_mm' => $variant->width_mm,
            'height_mm' => $variant->height_mm,
            // Stock físico de la bodega principal (lo que se vuelve a importar)
            'stock' => $variant->inventoryItem?->track_stock ? (int) $variant->levels->firstWhere('warehouse_id', $this->defaultWarehouseId())?->on_hand : null,
            'min_stock' => $variant->inventoryItem?->min_stock,
            'track_stock' => $variant->inventoryItem ? ($variant->inventoryItem->track_stock ? 'si' : 'no') : null,
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
        ];
        foreach ($options->take(3) as $i => $value) {
            $values['option'.($i + 1).'_name'] = $value->attribute->name;
            $values['option'.($i + 1).'_value'] = $value->label;
        }

        return array_map(fn ($field) => $values[$field] ?? '', array_keys(ImportColumns::FIELDS));
    }

    private function categoryName(Category $category): string
    {
        return $this->categoryNames[$category->id] ??= str_replace(' › ', ' > ', $category->fullName());
    }

    private ?int $defaultWarehouse = null;

    private function defaultWarehouseId(): int
    {
        return $this->defaultWarehouse ??= Warehouse::default()->id;
    }

    private function writer(string $format): WriterInterface
    {
        if ($format === 'xlsx') {
            return new XlsxWriter;
        }
        $options = new CsvOptions;
        $options->FIELD_DELIMITER = ';'; // Excel en español abre bien el punto y coma
        $options->SHOULD_ADD_BOM = true;

        return new CsvWriter($options);
    }
}
