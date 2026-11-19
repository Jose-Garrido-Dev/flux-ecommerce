<?php

namespace Tests\Feature\Language;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

/**
 * Complemento de SpanishOnlyAdminTest: abre los modales de las acciones (formularios que no están
 * en el HTML inicial) y envía formularios vacíos para revisar los mensajes de validación.
 */
class SpanishOnlyModalsTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private array $problems = [];

    private function check(string $where, Testable $component): void
    {
        foreach (SpanishOnlyAdminTest::englishFragments($component->html()) as $fragment) {
            $this->problems[] = "$where → \"$fragment\"";
        }
    }

    public function test_action_modals_and_validation_messages_are_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $this->attribute('color', ['negro', 'blanco']);
        $product = $this->product('Polera', [['price' => 9990, 'options' => ['color' => 'negro']]]);
        $category = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $brand = Brand::query()->create(['name' => 'Marca', 'slug' => 'marca']);
        $product->categories()->attach($category);
        $owner = ['ownerRecord' => $product, 'pageClass' => EditProduct::class];

        $this->check('Nuevo producto (vacío)', Livewire::test(CreateProduct::class)->call('create'));
        $this->check('Editar producto (vacío)', Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['name' => '', 'slug' => ''])->call('save'));
        foreach (['manual', 'publish', 'duplicate'] as $action) {
            $this->check("Producto: $action", Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->mountAction($action));
        }
        $this->check('Productos: filtros', Livewire::test(ListProducts::class)->mountTableBulkAction('publish', [$product]));

        $variants = fn () => Livewire::test(VariantsRelationManager::class, $owner);
        $this->check('Variantes: agregar', $variants()->mountTableAction('create'));
        $this->check('Variantes: agregar (vacío)', $variants()->callTableAction('create', data: []));
        $this->check('Variantes: generar', $variants()->mountTableAction('generate'));
        $this->check('Variantes: editar', $variants()->mountTableAction('edit', $product->variants()->first()));
        $this->check('Variantes: precio masivo', $variants()->mountTableBulkAction('changePrice', $product->variants()->get()));

        $this->check('Imágenes: subir', Livewire::test(MediaRelationManager::class, $owner)->mountTableAction('upload'));
        $this->check('Imágenes: biblioteca', Livewire::test(MediaRelationManager::class, $owner)->mountTableAction('attachExisting'));
        $this->check('Relacionados: agregar', Livewire::test(RelatedProductsRelationManager::class, $owner)->mountTableAction('create'));
        $this->check('Relacionados: agregar (vacío)', Livewire::test(RelatedProductsRelationManager::class, $owner)->callTableAction('create', data: []));

        $this->check('Marcas: nueva', Livewire::test(ManageBrands::class)->mountAction('create'));
        $this->check('Marcas: nueva (vacío)', Livewire::test(ManageBrands::class)->callAction('create', data: []));
        $this->check('Marcas: editar', Livewire::test(ManageBrands::class)->mountTableAction('edit', $brand));
        $this->check('Categorías: mover productos', Livewire::test(ListCategories::class)->mountTableAction('moveProducts', $category));
        $this->check('Atributos: listado', Livewire::test(ListAttributes::class));
        $this->check('Usuarios: nuevo', Livewire::test(ManageUsers::class)->mountAction('create'));
        $this->check('Usuarios: nuevo (vacío)', Livewire::test(ManageUsers::class)->callAction('create', data: []));

        $this->assertSame([], array_values(array_unique($this->problems)),
            "Texto en inglés en modales o validaciones:\n".implode("\n", array_unique($this->problems)));
    }
}
