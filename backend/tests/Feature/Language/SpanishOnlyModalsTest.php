<?php

namespace Tests\Feature\Language;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Filament\Pages\InventorySettings;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\InventoryLevels\Pages\ListInventoryLevels;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockReservations\Pages\ListStockReservations;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Warehouses\Pages\ManageWarehouses;
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

        // Inventario
        $variant = $product->variants()->first();
        $inventory = app(InventoryService::class);
        $inventory->item($variant)->update(['track_stock' => true]);
        $movement = $inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, 5, 'Compra');
        $reservation = $inventory->reserve($variant, 1, 'cart', 'c-1');
        $level = InventoryLevel::query()->where('variant_id', $variant->id)->firstOrFail();
        $this->check('Existencias: listado', Livewire::test(ListInventoryLevels::class));
        $this->check('Existencias: movimiento', Livewire::test(ListInventoryLevels::class)->mountAction('movement'));
        $this->check('Existencias: movimiento (vacío)', Livewire::test(ListInventoryLevels::class)->callAction('movement', data: []));
        $this->check('Existencias: salida mayor al stock', Livewire::test(ListInventoryLevels::class)->callAction('movement', data: [
            'variant_id' => $variant->id, 'warehouse_id' => $level->warehouse_id, 'type' => 'manual_out', 'quantity' => 99, 'reason' => 'x']));
        $this->check('Existencias: conteo', Livewire::test(ListInventoryLevels::class)->mountTableAction('count', $level));
        $this->check('Existencias: configurar', Livewire::test(ListInventoryLevels::class)->mountTableAction('configure', $level));
        $this->check('Existencias: filtros', Livewire::test(ListInventoryLevels::class)->filterTable('state', 'out'));
        $this->check('Movimientos: listado', Livewire::test(ListStockMovements::class));
        $this->check('Movimientos: revertir (vacío)', Livewire::test(ListStockMovements::class)->callTableAction('reverse', $movement, data: []));
        $this->check('Reservas: liberar', Livewire::test(ListStockReservations::class)->mountTableAction('release', $reservation));
        $this->check('Transferencias: nueva (vacía)', Livewire::test(CreateStockTransfer::class)->fillForm(['items' => []])->call('create'));
        $this->check('Bodegas: nueva', Livewire::test(ManageWarehouses::class)->mountAction('create'));
        $this->check('Bodegas: nueva (vacío)', Livewire::test(ManageWarehouses::class)->callAction('create', data: []));
        $this->check('Configuración de inventario (vacío)', Livewire::test(InventorySettings::class)->fillForm(['reservation_minutes' => null])->call('save'));
        $this->check('Variantes: inventario', Livewire::test(VariantsRelationManager::class, $owner)->mountTableAction('stockMovement', $variant));
        $this->check('Producto: registrar movimiento', Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])->mountAction('stockMovement'));

        // Ventas, clientes, envíos y pagos
        app(\App\Domain\System\Services\SettingsRepository::class)->putMany('payments', [
            'bank_transfer_enabled' => true, 'bank_name' => 'Banco', 'account_type' => 'Cuenta corriente', 'account_number' => '1',
            'account_holder' => 'Tienda', 'account_rut' => '76.123.456-0',
        ]);
        \App\Domain\Shipping\Models\ShippingMethod::query()->create(['code' => 'domicilio', 'name' => 'Despacho', 'type' => 'flat', 'amount' => 3990]);
        $inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, 5, 'Compra');
        [$cart] = app(\App\Domain\Cart\Services\CartService::class)->create();
        app(\App\Domain\Cart\Services\CartService::class)->setQuantity($cart, $variant->sku, 1);
        $order = app(\App\Domain\Orders\Services\PlaceOrder::class)->handle($cart, [
            'email' => 'ana@correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '912345678',
            'shipping_method' => 'domicilio', 'payment_method' => 'bank_transfer',
            'address' => ['recipient' => 'Ana', 'phone' => '912345678', 'street' => 'Los Leones', 'number' => '1', 'region_code' => 'CL-RM', 'commune' => 'Providencia'],
        ], null, 'clave');
        $customer = \App\Domain\Customers\Models\Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234']);
        $viewOrder = fn () => Livewire::test(\App\Filament\Resources\Orders\Pages\ViewOrder::class, ['record' => $order->getRouteKey()]);
        $this->check('Pedido: confirmar pago', $viewOrder()->mountAction('confirmPayment'));
        $this->check('Pedido: confirmar pago con monto distinto', $viewOrder()->callAction('confirmPayment', data: ['amount' => 1]));
        $this->check('Pedido: cancelar (vacío)', $viewOrder()->callAction('cancel', data: []));
        $this->check('Pedidos: filtros', Livewire::test(\App\Filament\Resources\Orders\Pages\ListOrders::class)->filterTable('status', 'pending'));
        $this->check('Cliente: bloquear', Livewire::test(\App\Filament\Resources\Customers\Pages\ViewCustomer::class, ['record' => $customer->getRouteKey()])->mountAction('block'));
        $this->check('Envíos: nuevo', Livewire::test(\App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods::class)->mountAction('create'));
        $this->check('Envíos: nuevo (vacío)', Livewire::test(\App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods::class)->callAction('create', data: ['type' => null]));
        $this->check('Pagos (vacío)', Livewire::test(\App\Filament\Pages\PaymentSettingsPage::class)->fillForm(['bank_transfer_enabled' => true, 'bank_transfer_hours' => null])->call('save'));

        $this->assertSame([], array_values(array_unique($this->problems)),
            "Texto en inglés en modales o validaciones:\n".implode("\n", array_unique($this->problems)));
    }
}
