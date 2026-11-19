<?php

namespace App\Console\Commands;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Pricing\Models\TaxClass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Catálogo de DEMOSTRACIÓN para desarrollo y pruebas visuales.
 *
 * Se niega a correr en producción (regla: nada de datos ficticios en producción).
 * Todo lo que crea queda marcado: SKU con prefijo DEMO-, slugs con prefijo demo-
 * e imágenes en la carpeta demo/. `--remove` lo borra sin tocar datos reales.
 */
class DemoCatalogCommand extends Command
{
    protected $signature = 'flux:demo {--remove : Elimina el catálogo de demostración}';

    protected $description = 'Crea (o elimina) un catálogo de demostración. Solo desarrollo.';

    private const PALETTE = [
        'negro' => ['Negro', '#1f1f1f'], 'blanco' => ['Blanco', '#f5f5f2'], 'arena' => ['Arena', '#d8c7a8'],
        'oliva' => ['Oliva', '#6b7046'], 'azul' => ['Azul marino', '#23324d'], 'terracota' => ['Terracota', '#b5603d'],
    ];

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('flux:demo no se puede usar en producción: la tienda solo debe mostrar datos reales.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $this->remove();
            $this->info('Catálogo de demostración eliminado.');

            return self::SUCCESS;
        }

        if (Product::query()->where('slug', 'like', 'demo-%')->exists()) {
            $this->warn('El catálogo de demostración ya existe. Usa --remove para borrarlo primero.');

            return self::SUCCESS;
        }

        mt_srand(2026);
        DB::transaction(fn () => $this->create());
        $this->info('Catálogo de demostración creado: '.Product::query()->where('slug', 'like', 'demo-%')->count().' productos.');
        $this->line('Para quitarlo: php artisan flux:demo --remove');

        return self::SUCCESS;
    }

    private function create(): void
    {
        $tax = TaxClass::query()->where('is_default', true)->first();

        $color = Attribute::query()->firstOrCreate(['code' => 'color'], [
            'name' => 'Color', 'type' => 'color', 'display' => 'swatch',
            'is_filterable' => true, 'is_variant_axis' => true, 'position' => 1,
        ]);
        $size = Attribute::query()->firstOrCreate(['code' => 'talla'], [
            'name' => 'Talla', 'type' => 'select', 'display' => 'pill',
            'is_filterable' => true, 'is_variant_axis' => true, 'position' => 2,
        ]);
        $material = Attribute::query()->firstOrCreate(['code' => 'material'], [
            'name' => 'Material', 'type' => 'select', 'display' => 'select',
            'is_filterable' => true, 'position' => 3,
        ]);

        $colors = [];
        $pos = 0;
        foreach (self::PALETTE as $value => [$label, $hex]) {
            $colors[$value] = AttributeValue::query()->firstOrCreate(
                ['attribute_id' => $color->id, 'value' => $value],
                ['label' => $label, 'color_hex' => $hex, 'position' => $pos++],
            );
        }
        $sizes = [];
        foreach (['s', 'm', 'l', 'xl'] as $i => $value) {
            $sizes[$value] = AttributeValue::query()->firstOrCreate(
                ['attribute_id' => $size->id, 'value' => $value],
                ['label' => Str::upper($value), 'position' => $i],
            );
        }
        $materials = [];
        foreach (['algodon' => 'Algodón orgánico', 'lino' => 'Lino', 'lana' => 'Lana merino', 'ceramica' => 'Cerámica', 'cuero' => 'Cuero vegetal'] as $i => $label) {
            $materials[$i] = AttributeValue::query()->firstOrCreate(
                ['attribute_id' => $material->id, 'value' => $i],
                ['label' => $label],
            );
        }

        $brands = collect(['Austral' => true, 'Cordillera Studio' => true, 'Bruma' => true, 'Litoral' => false])
            ->map(fn ($home, $name) => Brand::query()->create([
                'name' => $name, 'slug' => 'demo-'.Str::slug($name), 'show_on_home' => $home,
                'description' => 'Marca de demostración.',
            ]))->values();

        $tree = [
            'Ropa' => ['Poleras', 'Polerones', 'Pantalones'],
            'Accesorios' => ['Bolsos', 'Gorros'],
            'Hogar' => ['Tazas', 'Textiles'],
        ];
        $leaf = [];
        $p = 0;
        foreach ($tree as $parentName => $children) {
            $parent = Category::query()->create([
                'name' => $parentName, 'slug' => 'demo-'.Str::slug($parentName),
                'position' => $p++, 'on_home' => true,
                'description' => "Selección de $parentName de demostración.",
                'image_media_id' => $this->image("cat-$parentName", 1200, 900)->id,
            ]);
            foreach ($children as $i => $childName) {
                $leaf[$childName] = Category::query()->create([
                    'name' => $childName, 'slug' => 'demo-'.Str::slug($childName),
                    'parent_id' => $parent->id, 'position' => $i,
                ]);
            }
        }

        $catalog = [
            ['Polera Esencial', 'Poleras', 12990, 'algodon', ['negro', 'blanco', 'arena'], true],
            ['Polera Cuello Redondo Pesada', 'Poleras', 16990, 'algodon', ['blanco', 'oliva'], true],
            ['Polera Manga Larga Rib', 'Poleras', 18990, 'algodon', ['negro', 'terracota'], true],
            ['Polera Lino Verano', 'Poleras', 24990, 'lino', ['arena', 'blanco'], true],
            ['Polerón Canguro', 'Polerones', 34990, 'algodon', ['negro', 'oliva', 'azul'], true],
            ['Polerón Cierre Completo', 'Polerones', 39990, 'algodon', ['arena', 'azul'], true],
            ['Sweater Merino Liviano', 'Polerones', 54990, 'lana', ['arena', 'negro'], true],
            ['Pantalón Chino Recto', 'Pantalones', 32990, 'algodon', ['arena', 'oliva', 'azul'], true],
            ['Pantalón Lino Cordón', 'Pantalones', 36990, 'lino', ['blanco', 'arena'], true],
            ['Jogger Felpa', 'Pantalones', 27990, 'algodon', ['negro', 'oliva'], true],
            ['Bolso Tote Lona', 'Bolsos', 19990, 'algodon', ['arena', 'negro'], false],
            ['Mochila Urbana', 'Bolsos', 44990, 'cuero', ['negro', 'terracota'], false],
            ['Riñonera Compacta', 'Bolsos', 17990, 'cuero', ['negro', 'oliva'], false],
            ['Gorro Lana Doblado', 'Gorros', 14990, 'lana', ['negro', 'terracota', 'oliva', 'arena'], false],
            ['Jockey Seis Paneles', 'Gorros', 15990, 'algodon', ['azul', 'arena'], false],
            ['Taza Esmaltada 350 ml', 'Tazas', 8990, 'ceramica', ['blanco', 'terracota', 'oliva'], false],
            ['Set de 2 Tazas Espresso', 'Tazas', 11990, 'ceramica', ['negro'], false],
            ['Cojín Lino Lavado', 'Textiles', 22990, 'lino', ['arena', 'terracota', 'oliva'], false],
            ['Manta Tejida', 'Textiles', 49990, 'lana', ['arena'], false],
            ['Individuales Lino (4)', 'Textiles', 19990, 'lino', ['blanco', 'arena'], false],
        ];

        foreach ($catalog as $i => [$name, $cat, $price, $mat, $productColors, $hasSizes]) {
            $slug = 'demo-'.Str::slug($name);
            $product = Product::query()->create([
                'name' => $name,
                'slug' => $slug,
                'short_description' => "$name de demostración: diseño simple, materiales nobles y terminaciones cuidadas.",
                'description' => "<p>$name pensado para el uso diario.</p><ul><li>Material: {$materials[$mat]->label}</li><li>Hecho para durar</li><li>Producto de demostración</li></ul>",
                'brand_id' => $brands[$i % $brands->count()]->id,
                'primary_category_id' => $leaf[$cat]->id,
                'tax_class_id' => $tax?->id,
                'status' => ProductStatus::Active,
                'featured' => $i % 3 === 0,
                'has_variants' => count($productColors) > 1 || $hasSizes,
                'published_at' => now()->subDays(40 - $i),
            ]);
            $product->categories()->attach($leaf[$cat]->id);
            $product->attributeValues()->create(['attribute_id' => $material->id, 'attribute_value_id' => $materials[$mat]->id]);

            $vpos = 0;
            foreach ($productColors as $ci => $colorKey) {
                $hex = self::PALETTE[$colorKey][1];
                $front = $this->image("$slug-$colorKey-a", 1200, 1500, $hex);
                $back = $this->image("$slug-$colorKey-b", 1200, 1500, $hex, true);
                $product->media()->attach($front->id, ['position' => $ci * 2, 'is_primary' => $ci === 0]);
                $product->media()->attach($back->id, ['position' => $ci * 2 + 1, 'is_primary' => false]);

                foreach ($hasSizes ? ['s', 'm', 'l', 'xl'] : [null] as $sizeKey) {
                    $variant = ProductVariant::query()->create([
                        'product_id' => $product->id,
                        'sku' => Str::upper('DEMO-'.Str::substr(Str::slug($name, ''), 0, 10).'-'.$colorKey.($sizeKey ? "-$sizeKey" : '')),
                        'is_default' => $vpos === 0,
                        'position' => $vpos++,
                        'weight_g' => 300 + mt_rand(0, 900),
                        'image_media_id' => $front->id,
                    ]);
                    $variant->attributeValues()->attach($colors[$colorKey]->id, ['attribute_id' => $color->id]);
                    if ($sizeKey) {
                        $variant->attributeValues()->attach($sizes[$sizeKey]->id, ['attribute_id' => $size->id]);
                    }

                    $amount = $price + ($sizeKey === 'xl' ? 2000 : 0);
                    $onSale = $i % 4 === 1;
                    $variant->prices()->create([
                        'currency' => 'CLP',
                        'price_amount' => $amount,
                        'sale_amount' => $onSale ? (int) (round($amount * 0.8 / 10) * 10) : null,
                        'sale_ends_at' => $onSale ? now()->addDays(10) : null,
                        'compare_at_amount' => $i % 7 === 2 ? $amount + 5000 : null,
                    ]);
                }
            }
        }
    }

    private function remove(): void
    {
        DB::transaction(function () {
            $products = Product::query()->where('slug', 'like', 'demo-%')->get();
            $products->each->delete();

            Category::query()->where('slug', 'like', 'demo-%')->orderByDesc('depth')->get()->each->delete();
            Brand::query()->where('slug', 'like', 'demo-%')->delete();

            Media::query()->where('path', 'like', 'demo/%')->get()->each(function (Media $m) {
                Storage::disk($m->disk)->delete($m->path);
                $m->delete();
            });
        });
    }

    /** Imagen abstracta generada con GD (sin descargar nada de internet). */
    private function image(string $name, int $w, int $h, ?string $accent = null, bool $alt = false): Media
    {
        $img = imagecreatetruecolor($w, $h);
        $seed = crc32($name);
        $bg = [240 - ($seed % 12), 236 - ($seed % 10), 228 - ($seed % 14)];
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, ...$bg));

        [$r, $g, $b] = sscanf($accent ?? sprintf('#%06x', $seed & 0x7F7F7F), '#%02x%02x%02x');
        $main = imagecolorallocate($img, $r, $g, $b);
        $shade = imagecolorallocate($img, (int) ($r * 0.8), (int) ($g * 0.8), (int) ($b * 0.8));
        $soft = imagecolorallocatealpha($img, $r, $g, $b, 100);

        if ($alt) {
            imagefilledellipse($img, (int) ($w * 0.5), (int) ($h * 0.52), (int) ($w * 0.78), (int) ($w * 0.78), $soft);
            imagefilledrectangle($img, (int) ($w * 0.3), (int) ($h * 0.3), (int) ($w * 0.7), (int) ($h * 0.74), $main);
            imagefilledrectangle($img, (int) ($w * 0.3), (int) ($h * 0.66), (int) ($w * 0.7), (int) ($h * 0.74), $shade);
        } else {
            imagefilledellipse($img, (int) ($w * 0.5), (int) ($h * 0.86), (int) ($w * 0.7), (int) ($h * 0.06), imagecolorallocatealpha($img, 0, 0, 0, 105));
            imagefilledrectangle($img, (int) ($w * 0.26), (int) ($h * 0.22), (int) ($w * 0.74), (int) ($h * 0.82), $main);
            imagefilledrectangle($img, (int) ($w * 0.26), (int) ($h * 0.22), (int) ($w * 0.74), (int) ($h * 0.3), $shade);
        }

        ob_start();
        imagejpeg($img, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($img);

        $path = 'demo/'.Str::slug($name).'.jpg';
        Storage::disk('public')->put($path, $bytes);

        return Media::query()->create([
            'disk' => 'public', 'path' => $path, 'mime' => 'image/jpeg', 'size' => strlen($bytes),
            'width' => $w, 'height' => $h, 'checksum' => hash('sha256', $bytes),
        ]);
    }
}
