<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Jobs\RunCatalogImport;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\CatalogImport;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\MediaUploader;
use App\Domain\Orders\Models\Order;
use App\Filament\Pages\ImportProducts;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->user('catalog-manager'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    public function test_creates_simple_product_with_price_and_publishes_it(): void
    {
        $category = Category::query()->create(['name' => 'Hogar', 'slug' => 'hogar']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Taza de cerámica',
                'slug' => 'taza-de-ceramica',
                'status' => 'active',
                'primary_category_id' => $category->id,
                'default_variant' => ['sku' => 'TAZ-001', 'price_amount' => 8990, 'cost_amount' => 3000, 'weight_g' => 350],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'taza-de-ceramica')->firstOrFail();
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNotNull($product->published_at);
        $this->assertTrue($product->categories->contains($category), 'La categoría principal queda en la tabla de categorías');
        $variant = $product->variants()->firstOrFail();
        $this->assertSame('TAZ-001', $variant->sku);
        $this->assertSame(8990, $variant->priceIn('CLP')->price_amount);
        $this->assertSame(350, $variant->weight_g);
    }

    public function test_price_rules_and_duplicate_sku_are_shown_on_the_field(): void
    {
        $this->product('Existente', [['price' => 1000]]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Otro', 'slug' => 'otro',
                'default_variant' => ['sku' => 'X-1', 'price_amount' => 5000, 'sale_amount' => 6000, 'compare_at_amount' => 4000],
            ])
            ->call('create')
            ->assertHasFormErrors(['default_variant.sale_amount', 'default_variant.compare_at_amount']);

        Livewire::test(CreateProduct::class)
            ->fillForm(['name' => 'Otro', 'slug' => 'otro', 'default_variant' => ['sku' => 'EXISTENTE-0', 'price_amount' => 5000]])
            ->call('create')
            ->assertHasFormErrors(['default_variant.sku']);

        $this->assertSame(1, Product::query()->count(), 'Nada queda a medio guardar');
    }

    public function test_edit_keeps_variant_data_and_updates_price(): void
    {
        $product = $this->product('Polera', [['price' => 9990]]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(['default_variant.sku' => 'POLERA-0', 'default_variant.price_amount' => 9990])
            ->fillForm(['default_variant.price_amount' => 7990])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(7990, $product->variants()->first()->priceIn('CLP')->price_amount);
    }

    public function test_generates_variant_combinations_and_replaces_placeholder(): void
    {
        $color = $this->attribute('color', ['negro', 'blanco']);
        $talla = $this->attribute('talla', ['s', 'm', 'l']);
        $product = $this->product('Polera', [['price' => 9990]]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('generate', data: [
                'selection' => [
                    $color->id => $color->values->pluck('id')->all(),
                    $talla->id => $talla->values->pluck('id')->all(),
                ],
                'price_amount' => 10990,
                'sku_prefix' => 'POL',
            ])
            ->assertHasNoTableActionErrors();

        $product->refresh();
        $this->assertTrue($product->has_variants);
        $this->assertSame(6, $product->variants()->count());
        $this->assertTrue(ProductVariant::query()->where('sku', 'POL-NEGRO-M')->exists());
        $this->assertSame(1, $product->variants()->where('is_default', true)->count());
    }

    public function test_duplicate_creates_draft_copy(): void
    {
        $product = $this->product('Mochila', [['price' => 25990]]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('duplicate');

        $copy = Product::query()->where('slug', 'mochila-copia')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $copy->status);
        $this->assertSame('MOCHILA-0-COPIA', $copy->variants()->value('sku'));
    }

    public function test_bulk_publish_reports_products_without_price(): void
    {
        $ok = $this->product('Con precio', [['price' => 1000]], ['status' => ProductStatus::Draft]);
        $empty = Product::query()->create(['name' => 'Sin precio', 'slug' => 'sin-precio']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('publish', [$ok, $empty]);

        $this->assertSame(ProductStatus::Active, $ok->fresh()->status);
        $this->assertSame(ProductStatus::Draft, $empty->fresh()->status);
    }

    public function test_product_with_orders_cannot_be_deleted(): void
    {
        $product = $this->product('Vendido', [['price' => 1000]]);
        $variant = $product->variants()->first();
        $order = Order::query()->create([
            'email' => 'ana@correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez',
            'subtotal' => 1000, 'tax_total' => 160, 'shipping_total' => 0, 'grand_total' => 1000,
            'tracking_token_hash' => hash('sha256', 'prueba'),
        ]);
        $order->items()->create([
            'variant_id' => $variant->id, 'product_name' => 'Vendido', 'sku' => $variant->sku, 'quantity' => 1,
            'unit_price' => 1000, 'tax_rate_bp' => 1900, 'tax_amount' => 160, 'line_total' => 1000,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertNotNull($product->fresh(), 'Se conserva por el historial de pedidos');

        $free = $this->product('Libre', [['price' => 1000]]);
        Livewire::test(EditProduct::class, ['record' => $free->getRouteKey()])
            ->callAction(DeleteAction::class);
        $this->assertNull($free->fresh());
    }

    public function test_category_cycle_is_rejected(): void
    {
        $parent = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $child = Category::query()->create(['name' => 'Poleras', 'slug' => 'poleras', 'parent_id' => $parent->id]);

        Livewire::test(EditCategory::class, ['record' => $parent->getRouteKey()])
            ->fillForm(['parent_id' => $child->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_brand_names_are_not_duplicated_with_different_spelling(): void
    {
        Brand::query()->create(['name' => 'Ñandú', 'slug' => 'nandu']);

        Livewire::test(ManageBrands::class)
            ->callAction(CreateAction::class, data: ['name' => ' nandu ', 'slug' => 'nandu-2', 'status' => 'active'])
            ->assertHasActionErrors(['name']);

        $this->assertSame(1, Brand::query()->count());
    }

    public function test_media_upload_rejects_files_that_are_not_images(): void
    {
        $fake = UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo "hola";');

        $this->expectException(ValidationException::class);
        app(MediaUploader::class)->store($fake->getRealPath(), 'foto.jpg');
    }

    public function test_media_upload_stores_optimized_image_with_thumbnail(): void
    {
        $product = $this->product('Lámpara', [['price' => 1000]]);
        $image = UploadedFile::fake()->image('lampara.jpg', 3000, 2000);

        Livewire::test(MediaRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('upload', data: ['files' => [$image]])
            ->assertHasNoTableActionErrors();

        $media = $product->media()->firstOrFail();
        $this->assertSame(2400, $media->width, 'Se reduce a 2400 px');
        $this->assertTrue((bool) $media->pivot->is_primary);
        Storage::disk('public')->assertExists([$media->path, $media->variants['thumb']]);
    }

    public function test_import_page_analyzes_then_queues_the_import(): void
    {
        Queue::fake();
        $csv = "sku;nombre;precio\nA-1;Producto A;1000\nA-2;Producto B;abc\n";

        Livewire::test(ImportProducts::class)
            ->set('upload.file', UploadedFile::fake()->createWithContent('productos.csv', $csv))
            ->set('upload.mode', 'upsert')
            ->call('analyze')
            ->assertSee('Filas con errores')
            ->assertSee('no es un monto válido')
            ->call('confirm');

        $import = CatalogImport::query()->firstOrFail();
        $this->assertSame('queued', $import->status);
        $this->assertSame(1, $import->summary['variants_to_create']);
        Queue::assertPushed(RunCatalogImport::class);
        $this->assertSame(0, Product::query()->count(), 'Analizar no escribe en el catálogo');
    }

    public function test_roles_without_permission_cannot_manage_catalog(): void
    {
        $this->actingAs($this->user('inventory'));

        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertForbidden();
        $this->get('/admin/import-products')->assertForbidden();
        $this->get('/admin/brands')->assertForbidden();

        $this->actingAs($this->user('sales'));
        $this->get('/admin/products')->assertForbidden();
    }
}
