<?php

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Import\CatalogExporter;
use App\Domain\Catalog\Import\CatalogImporter;
use App\Domain\Catalog\Import\ImportColumns;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Database\Seeders\TaxClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(TaxClassSeeder::class);
    }

    private function csv(array $rows, string $delimiter = ';', ?array $headers = null): CatalogImport
    {
        $headers ??= ImportColumns::headers();
        $lines = [implode($delimiter, $headers)];
        foreach ($rows as $row) {
            $lines[] = implode($delimiter, array_map(
                fn ($h) => str_contains((string) ($row[$h] ?? ''), $delimiter) ? '"'.$row[$h].'"' : ($row[$h] ?? ''),
                $headers,
            ));
        }
        Storage::disk('local')->put('imports/test.csv', implode("\n", $lines));

        return CatalogImport::query()->create(['original_name' => 'test.csv', 'path' => 'imports/test.csv', 'mode' => 'upsert']);
    }

    private function importer(): CatalogImporter
    {
        return app(CatalogImporter::class);
    }

    public function test_imports_1000_products_with_errors_reported_per_row(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = [
                'sku' => "SKU-$i", 'nombre' => "Producto $i", 'marca' => $i % 2 ? 'Marca Uno' : 'marca  uno',
                'categoria' => 'Hogar > Cocina', 'precio' => (string) (1000 + $i), 'estado' => 'publicado',
                'peso_g' => '250',
            ];
        }
        // 3 filas con errores distintos
        $rows[10]['precio'] = 'abc';
        $rows[20]['sku'] = 'SKU-1';             // repetido
        $rows[30]['precio_oferta'] = '999999';  // oferta mayor al precio

        $import = $this->csv($rows);
        $this->importer()->analyze($import);
        $import->refresh();

        $this->assertSame('analyzed', $import->status);
        $this->assertSame(1000, $import->total_rows);
        $this->assertSame(997, $import->summary['variants_to_create']);
        $this->assertSame(3, $import->summary['rows_with_errors']);
        $this->assertSame(0, Product::query()->count(), 'El análisis no escribe nada');

        $started = microtime(true);
        $this->importer()->run($import);
        $import->refresh();

        $this->assertSame('done', $import->status);
        $this->assertSame(997, $import->created_count);
        $this->assertSame(3, $import->failed_count);
        $this->assertSame([12, 22, 32], array_column($import->errors, 'row'));
        $this->assertStringContainsString('no es un monto válido', $import->errors[0]['message']);
        $this->assertStringContainsString('SKU repetido', $import->errors[1]['message']);
        $this->assertLessThan(120, microtime(true) - $started);

        $this->assertSame(997, Product::query()->count());
        $this->assertSame(1, Brand::query()->count(), 'Marcas con distinta escritura no se duplican');
        $this->assertSame(2, Category::query()->count());
        $this->assertSame(997, Product::query()->visible()->count());

        $report = $this->importer()->errorReportCsv($import);
        $this->assertStringContainsString('12;SKU-11;', $report);
    }

    public function test_variants_grouped_by_slug_and_update_by_sku_keeps_blank_cells(): void
    {
        $import = $this->csv([
            ['sku' => 'POL-N-S', 'nombre' => 'Polera', 'slug' => 'polera', 'precio' => '9990', 'opcion1_nombre' => 'Color', 'opcion1_valor' => 'Negro', 'opcion2_nombre' => 'Talla', 'opcion2_valor' => 'S', 'costo' => '4000', 'descripcion_corta' => 'Algodón'],
            ['sku' => 'POL-N-M', 'nombre' => 'Polera', 'slug' => 'polera', 'precio' => '9990', 'opcion1_nombre' => 'Color', 'opcion1_valor' => 'Negro', 'opcion2_nombre' => 'Talla', 'opcion2_valor' => 'M'],
            ['sku' => 'POL-B-S', 'nombre' => 'Polera', 'slug' => 'polera', 'precio' => '10990', 'opcion1_nombre' => 'Color', 'opcion1_valor' => 'Blanco', 'opcion2_nombre' => 'Talla', 'opcion2_valor' => 'S'],
        ]);
        $this->importer()->analyze($import);
        $this->importer()->run($import);

        $product = Product::query()->where('slug', 'polera')->firstOrFail();
        $this->assertTrue($product->has_variants);
        $this->assertSame(3, $product->variants()->count());
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertTrue(Attribute::query()->where('code', 'color')->value('is_variant_axis'));
        $this->assertSame('swatch', Attribute::query()->where('code', 'color')->value('display'));

        // Segunda importación: solo cambia el precio de un SKU; las celdas vacías no borran datos
        $update = $this->csv([['sku' => 'POL-N-S', 'nombre' => 'Polera', 'slug' => 'polera', 'precio' => '8990']]);
        $this->importer()->analyze($update);
        $this->assertSame(1, $update->fresh()->summary['variants_to_update']);
        $this->importer()->run($update);

        $variant = ProductVariant::query()->where('sku', 'POL-N-S')->firstOrFail();
        $this->assertSame(8990, $variant->priceIn('CLP')->price_amount);
        $this->assertSame(4000, $variant->priceIn('CLP')->cost_amount);
        $this->assertSame('Algodón', $product->fresh()->short_description);
        $this->assertSame(1, $update->fresh()->updated_count);
    }

    public function test_modes_and_duplicate_options_are_reported(): void
    {
        $this->csv([['sku' => 'A-1', 'nombre' => 'A', 'precio' => '1000']]);
        $first = CatalogImport::query()->first();
        $this->importer()->analyze($first);
        $this->importer()->run($first);

        $import = $this->csv([
            ['sku' => 'A-1', 'nombre' => 'A', 'precio' => '1200'],
            ['sku' => 'B-1', 'nombre' => 'B', 'slug' => 'b', 'precio' => '1000', 'opcion1_nombre' => 'Talla', 'opcion1_valor' => 'M'],
            ['sku' => 'B-2', 'nombre' => 'B', 'slug' => 'b', 'precio' => '1000', 'opcion1_nombre' => 'Talla', 'opcion1_valor' => 'M'],
        ]);
        $import->update(['mode' => 'create']);
        $this->importer()->analyze($import);
        $messages = array_column($import->fresh()->errors, 'message');

        $this->assertContains('El SKU ya existe y el modo es "solo crear".', $messages);
        $this->assertTrue(collect($messages)->contains(fn ($m) => str_contains($m, 'Repite las mismas opciones')));
    }

    public function test_xlsx_import_and_round_trip_export(): void
    {
        $path = Storage::disk('local')->path('imports/test.xlsx');
        @mkdir(dirname($path), 0777, true);
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['SKU', 'Nombre', 'Precio', 'Marca', 'EAN']));
        $writer->addRow(Row::fromValues(['TAZ-1', 'Taza', 8990, 'Litoral', '7801234567890']));
        $writer->close();

        $import = CatalogImport::query()->create(['original_name' => 'test.xlsx', 'path' => 'imports/test.xlsx', 'mode' => 'upsert']);
        $this->importer()->analyze($import);
        $this->assertSame(['SKU' => 'sku', 'Nombre' => 'name', 'Precio' => 'price', 'Marca' => 'brand', 'EAN' => 'barcode'], $import->fresh()->mapping);
        $this->importer()->run($import);

        $variant = ProductVariant::query()->where('sku', 'TAZ-1')->firstOrFail();
        $this->assertSame('7801234567890', $variant->barcode);
        $this->assertSame(8990, $variant->priceIn('CLP')->price_amount);

        // La exportación usa el mismo formato y se puede volver a importar
        $export = Storage::disk('local')->path('imports/export.csv');
        app(CatalogExporter::class)->export($export, 'csv');
        $content = file_get_contents($export);
        $this->assertStringStartsWith("\xEF\xBB\xBFsku;nombre;", $content);
        $this->assertStringContainsString('TAZ-1;Taza;taza', $content);

        $reimport = CatalogImport::query()->create(['original_name' => 'export.csv', 'path' => 'imports/export.csv', 'mode' => 'update']);
        $this->importer()->analyze($reimport);
        $this->assertSame(0, $reimport->fresh()->summary['rows_with_errors']);
        $this->assertSame(1, $reimport->fresh()->summary['variants_to_update']);
    }

    public function test_windows_1252_csv_with_comma_delimiter(): void
    {
        $content = mb_convert_encoding("sku,nombre,precio\nCAF-1,Café de grano,5990\n", 'Windows-1252', 'UTF-8');
        Storage::disk('local')->put('imports/latin.csv', $content);
        $import = CatalogImport::query()->create(['original_name' => 'latin.csv', 'path' => 'imports/latin.csv', 'mode' => 'upsert']);

        $this->importer()->analyze($import);
        $this->importer()->run($import);

        $this->assertSame('Café de grano', Product::query()->value('name'));
    }
}
