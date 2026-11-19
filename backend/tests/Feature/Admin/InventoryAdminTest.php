<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Pages\InventorySettings;
use App\Filament\Resources\InventoryLevels\Pages\ListInventoryLevels;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockReservations\Pages\ListStockReservations;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\Warehouses\Pages\ManageWarehouses;
use App\Filament\Widgets\InventoryAlerts;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

class InventoryAdminTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->inventory = app(InventoryService::class);
        $this->actingAs($this->user('inventory'));
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole($role);

        return $user;
    }

    /** Producto simple que controla stock, con N unidades en la bodega principal. */
    private function stocked(string $name, int $units, int $min = 0): Product
    {
        $product = $this->product($name, [['price' => 5000]]);
        $variant = $product->variants()->first();
        $this->inventory->item($variant)->update(['track_stock' => true, 'min_stock' => $min]);
        if ($units > 0) {
            $this->inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, $units, 'Compra');
        }

        return $product;
    }

    private function level(Product $product, ?Warehouse $warehouse = null): InventoryLevel
    {
        return InventoryLevel::query()->where('variant_id', $product->variants()->value('id'))
            ->where('warehouse_id', ($warehouse ?? Warehouse::default())->id)->firstOrFail();
    }

    public function test_inventory_screens_open_for_the_inventory_role_and_not_for_others(): void
    {
        $product = $this->stocked('Taza', 5);
        $transfer = $this->inventory->transfer(Warehouse::default(),
            Warehouse::query()->create(['name' => 'Tienda', 'code' => 'TIENDA']), [['variant_id' => $product->variants()->value('id'), 'quantity' => 1]]);
        $pages = ['/admin/inventory', '/admin/stock-movements', '/admin/stock-transfers', '/admin/stock-transfers/create',
            '/admin/stock-transfers/'.$transfer->id, '/admin/stock-reservations', '/admin/warehouses'];

        foreach ($pages as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/inventory')->assertSee('Taza');
        $this->get('/admin/inventory-settings')->assertForbidden(); // requiere settings.manage

        $this->actingAs($this->user('catalog-manager'));
        foreach ($pages as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_creates_product_with_initial_stock_as_a_movement(): void
    {
        $this->actingAs($this->user('catalog-manager'));

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Taza', 'slug' => 'taza',
                'default_variant' => ['sku' => 'TAZ-1', 'price_amount' => 8990],
                'inventory' => ['track_stock' => true, 'allow_backorder' => false, 'min_stock' => 2, 'initial_stock' => 12, 'warehouse_id' => Warehouse::default()->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $variant = Product::query()->where('slug', 'taza')->firstOrFail()->variants()->first();
        $this->assertSame(2, $this->inventory->item($variant)->min_stock);
        $this->assertSame(12, $this->inventory->available($variant));
        $movement = StockMovement::query()->where('variant_id', $variant->id)->sole();
        $this->assertSame(MovementType::ManualIn, $movement->type);
        $this->assertSame('Stock inicial', $movement->reason);
    }

    public function test_product_inventory_tab_loads_and_saves_settings(): void
    {
        $product = $this->stocked('Polera', 4, 1);
        $this->actingAs($this->user('catalog-manager'));

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSchemaStateSet(['inventory.track_stock' => true, 'inventory.min_stock' => 1])
            ->fillForm(['inventory.min_stock' => 3, 'inventory.allow_backorder' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $item = $this->inventory->item($product->variants()->first());
        $this->assertSame(3, $item->min_stock);
        $this->assertTrue($item->allow_backorder);
        $this->assertSame(4, $this->level($product)->on_hand, 'Guardar el producto no toca el stock');
    }

    public function test_registers_manual_movements_and_rejects_exits_beyond_available(): void
    {
        $product = $this->stocked('Taza', 5);
        $variantId = $product->variants()->value('id');

        Livewire::test(ListInventoryLevels::class)
            ->callAction('movement', data: [
                'variant_id' => $variantId, 'warehouse_id' => Warehouse::default()->id,
                'type' => 'shrinkage', 'quantity' => 2, 'reason' => 'Rota', 'reference' => '',
            ])
            ->assertHasNoActionErrors();
        $this->assertSame(3, $this->level($product)->on_hand);

        Livewire::test(ListInventoryLevels::class)
            ->callAction('movement', data: [
                'variant_id' => $variantId, 'warehouse_id' => Warehouse::default()->id,
                'type' => 'manual_out', 'quantity' => 9, 'reason' => 'Error',
            ])
            ->assertHasActionErrors(['quantity']);
        $this->assertSame(3, $this->level($product)->on_hand);
        $this->assertSame(2, StockMovement::query()->count(), 'La salida rechazada no deja movimiento');
    }

    public function test_count_configure_and_reverse(): void
    {
        $product = $this->stocked('Taza', 5);
        $level = $this->level($product);

        Livewire::test(ListInventoryLevels::class)
            ->callTableAction('count', $level, data: ['counted' => 7, 'reason' => ''])
            ->assertHasNoTableActionErrors();
        $this->assertSame(7, $level->fresh()->on_hand);
        $adjust = StockMovement::query()->latest('id')->first();
        $this->assertSame(MovementType::AdjustIn, $adjust->type);
        $this->assertSame(2, $adjust->quantity);

        Livewire::test(ListInventoryLevels::class)
            ->callTableAction('configure', $level, data: ['track_stock' => true, 'allow_backorder' => false, 'min_stock' => 10]);
        $this->assertSame(10, $this->inventory->item($level->variant_id)->min_stock);

        Livewire::test(ListStockMovements::class)
            ->assertTableActionVisible('reverse', $adjust)
            ->callTableAction('reverse', $adjust, data: ['reason' => 'Conteo mal hecho'])
            ->assertHasNoTableActionErrors();
        $this->assertSame(5, $level->fresh()->on_hand);
        Livewire::test(ListStockMovements::class)->assertTableActionHidden('reverse', $adjust->fresh());
        $this->assertSame(5, StockMovement::query()->where('id', $adjust->id)->value('after_on_hand') - 2, 'El original no cambia');
    }

    public function test_state_filter_matches_alerts(): void
    {
        $out = $this->stocked('Agotado', 0);
        $low = $this->stocked('Poco', 2, 5);
        $ok = $this->stocked('Lleno', 50, 5);

        Livewire::test(ListInventoryLevels::class)
            ->filterTable('state', 'out')
            ->assertCanSeeTableRecords([$this->level($out)])
            ->assertCanNotSeeTableRecords([$this->level($low), $this->level($ok)]);
        Livewire::test(ListInventoryLevels::class)
            ->filterTable('state', 'low')
            ->assertCanSeeTableRecords([$this->level($low)])
            ->assertCanNotSeeTableRecords([$this->level($out), $this->level($ok)]);

        Livewire::test(InventoryAlerts::class)->assertSee('Sin stock')->assertSee('Stock bajo');

        $this->actingAs($this->user('catalog-manager'));
        Livewire::test(ListProducts::class)
            ->filterTable('stock', 'out')
            ->assertCanSeeTableRecords([$out])
            ->assertCanNotSeeTableRecords([$low, $ok]);
    }

    public function test_transfer_moves_stock_or_fails_without_changes(): void
    {
        $product = $this->stocked('Taza', 5);
        $store = Warehouse::query()->create(['name' => 'Tienda', 'code' => 'TIENDA']);
        $variantId = $product->variants()->value('id');

        Livewire::test(CreateStockTransfer::class)
            ->fillForm(['from_warehouse_id' => Warehouse::default()->id, 'to_warehouse_id' => $store->id, 'items' => [['variant_id' => $variantId, 'quantity' => 8]]])
            ->call('create')
            ->assertHasFormErrors(['items']);
        $this->assertSame(0, StockTransfer::query()->count());

        Livewire::test(CreateStockTransfer::class)
            ->fillForm(['from_warehouse_id' => Warehouse::default()->id, 'to_warehouse_id' => $store->id, 'items' => [['variant_id' => $variantId, 'quantity' => 3]]])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(2, $this->level($product)->on_hand);
        $this->assertSame(3, $this->level($product, $store)->on_hand);
    }

    public function test_release_reservation_from_the_panel(): void
    {
        $product = $this->stocked('Taza', 2);
        $reservation = $this->inventory->reserve($product->variants()->first(), 2, 'cart', 'c-1');

        Livewire::test(ListStockReservations::class)
            ->assertCanSeeTableRecords([$reservation])
            ->callTableAction('release', $reservation, data: ['reason' => 'Cliente lo pidió'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('released', $reservation->fresh()->status);
        $this->assertSame(0, $this->level($product)->reserved);
        Livewire::test(ListStockReservations::class)->assertCanNotSeeTableRecords([$reservation]); // filtro "Activa" por defecto
    }

    public function test_warehouses_keep_a_single_default_and_protect_history(): void
    {
        $old = Warehouse::default();
        Livewire::test(ManageWarehouses::class)
            ->callAction('create', data: ['name' => 'Central', 'code' => 'central', 'status' => 'active', 'priority' => 0, 'is_default' => true])
            ->assertHasNoActionErrors();

        $new = Warehouse::query()->where('code', 'CENTRAL')->firstOrFail();
        $this->assertTrue($new->is_default);
        $this->assertFalse($old->fresh()->is_default);

        // La principal no se puede eliminar; una con movimientos tampoco
        Livewire::test(ManageWarehouses::class)->callTableAction(DeleteAction::class, $new);
        $this->assertModelExists($new);
        $this->stocked('Taza', 1);
        Livewire::test(ManageWarehouses::class)->callTableAction(DeleteAction::class, $new);
        $this->assertModelExists($new);

        $empty = Warehouse::query()->create(['name' => 'Vacía', 'code' => 'VACIA']);
        InventoryLevel::query()->create(['variant_id' => Product::query()->first()->variants()->value('id'), 'warehouse_id' => $empty->id]);
        Livewire::test(ManageWarehouses::class)->callTableAction(DeleteAction::class, $empty);
        $this->assertModelMissing($empty);
    }

    public function test_variant_inventory_actions_in_the_product(): void
    {
        $this->attribute('talla', ['s', 'm']);
        $product = $this->product('Polera', [['price' => 9990, 'options' => ['talla' => 's']], ['price' => 9990, 'options' => ['talla' => 'm']]]);
        $variant = $product->variants()->first();
        $this->inventory->item($variant)->update(['track_stock' => true]);
        $owner = ['ownerRecord' => $product, 'pageClass' => EditProduct::class];

        Livewire::test(VariantsRelationManager::class, $owner)
            ->callTableAction('stockMovement', $variant, data: ['warehouse_id' => Warehouse::default()->id, 'type' => 'purchase_in', 'quantity' => 6, 'reason' => 'Compra'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('stockConfigure', $variant, data: ['track_stock' => true, 'allow_backorder' => false, 'min_stock' => 2]);

        $this->assertSame(6, $this->inventory->available($variant));
        $this->assertSame(2, $this->inventory->item($variant)->min_stock);
    }

    public function test_inventory_settings(): void
    {
        $this->actingAs($this->user('store-admin'));

        Livewire::test(InventorySettings::class)
            ->assertSchemaStateSet(['reservation_minutes' => 30])
            ->fillForm(['reservation_minutes' => 45, 'track_stock_default' => false, 'min_stock_default' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(45, $this->inventory->reservationMinutes());
        $this->assertFalse((bool) app(SettingsRepository::class)->get('inventory', 'track_stock_default'));
    }
}
