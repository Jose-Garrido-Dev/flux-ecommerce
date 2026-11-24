<?php

namespace Tests\Feature\Language;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Media;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Theme;
use App\Domain\Content\Services\PageService;
use App\Domain\Content\Services\RedirectService;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Marketing\Models\Automation;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Models\Promotion;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\PlaceOrder;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Pages\InventorySettings;
use App\Filament\Pages\PaymentSettingsPage;
use App\Filament\Pages\ReturnSettingsPage;
use App\Filament\Pages\ShippingSimulator;
use App\Filament\Pages\ThemesPage;
use App\Filament\Pages\WhatsAppSettingsPage;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Automations\Pages\ManageAutomations;
use App\Filament\Resources\BlogCategories\Pages\ManageBlogCategories;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
use App\Filament\Resources\Couriers\Pages\ManageCouriers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\InventoryLevels\Pages\ListInventoryLevels;
use App\Filament\Resources\MediaFolders\Pages\ManageMediaFolders;
use App\Filament\Resources\MediaLibrary\Pages\ManageMedia;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\RelationManagers\VersionsRelationManager;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Promotions\Pages\ManagePromotions;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Resources\Returns\Pages\ViewReturn;
use App\Filament\Resources\Shipments\Pages\ViewShipment;
use App\Filament\Resources\ShippingIncidents\Pages\ManageShippingIncidents;
use App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods;
use App\Filament\Resources\ShippingZones\Pages\ManageShippingZones;
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
        app(SettingsRepository::class)->putMany('payments', [
            'bank_transfer_enabled' => true, 'bank_name' => 'Banco', 'account_type' => 'Cuenta corriente', 'account_number' => '1',
            'account_holder' => 'Tienda', 'account_rut' => '76.123.456-0',
        ]);
        ShippingMethod::query()->create(['code' => 'domicilio', 'name' => 'Despacho', 'type' => 'flat', 'amount' => 3990]);
        $inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, 5, 'Compra');
        [$cart] = app(CartService::class)->create();
        app(CartService::class)->setQuantity($cart, $variant->sku, 1);
        $order = app(PlaceOrder::class)->handle($cart, [
            'email' => 'ana@correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '912345678',
            'shipping_method' => 'domicilio', 'payment_method' => 'bank_transfer',
            'address' => ['recipient' => 'Ana', 'phone' => '912345678', 'street' => 'Los Leones', 'number' => '1', 'region_code' => 'CL-RM', 'commune' => 'Providencia'],
        ], null, 'clave');
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234']);
        $viewOrder = fn () => Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()]);
        $this->check('Pedido: confirmar pago', $viewOrder()->mountAction('confirmPayment'));
        $this->check('Pedido: confirmar pago con monto distinto', $viewOrder()->callAction('confirmPayment', data: ['amount' => 1]));
        $this->check('Pedido: cancelar (vacío)', $viewOrder()->callAction('cancel', data: []));
        $this->check('Pedidos: filtros', Livewire::test(ListOrders::class)->filterTable('status', 'pending'));
        $this->check('Cliente: bloquear', Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])->mountAction('block'));
        $this->check('Envíos: nuevo', Livewire::test(ManageShippingMethods::class)->mountAction('create'));
        $this->check('Envíos: nuevo (vacío)', Livewire::test(ManageShippingMethods::class)->callAction('create', data: ['type' => null]));
        app(OrderPayments::class)->confirm($order, $order->grand_total);
        $this->check('Pedido: reembolsar', $viewOrder()->mountAction('refund'));
        $this->check('Pedido: reembolsar (vacío)', $viewOrder()->callAction('refund', data: ['amount' => null, 'reason' => null]));
        $this->check('Pedido: reembolsar de más', $viewOrder()->callAction('refund', data: ['amount' => 999999, 'reason' => 'x', 'restock' => false]));
        // Logística
        $this->check('Zonas: nueva', Livewire::test(ManageShippingZones::class)->mountAction('create'));
        $this->check('Zonas: nueva (vacía)', Livewire::test(ManageShippingZones::class)->callAction('create', data: []));
        $this->check('Envíos: tarifas por zona (vacío)', Livewire::test(ManageShippingMethods::class)
            ->callAction('create', data: ['type' => 'table', 'code' => 'tabla', 'name' => 'Tabla', 'rates' => [['zone_id' => null, 'min_weight_g' => null, 'amount' => null]]]));
        $starken = Courier::for('starken');
        $this->check('Couriers: editar', Livewire::test(ManageCouriers::class)->mountTableAction('edit', $starken));
        $this->check('Couriers: enlace sin número', Livewire::test(ManageCouriers::class)->callTableAction('edit', $starken, data: ['tracking_url_template' => 'https://ejemplo.cl/seguimiento']));
        $this->check('Couriers: probar conexión', Livewire::test(ManageCouriers::class)->callTableAction('verify', $starken));
        $this->check('Simulador (vacío)', Livewire::test(ShippingSimulator::class)->fillForm(['region_id' => null, 'commune' => null])->call('simulate'));
        $this->check('Simulador', Livewire::test(ShippingSimulator::class)
            ->fillForm(['region_id' => Region::query()->first()->id, 'commune' => 'Providencia', 'weight_kg' => 80])->call('simulate'));
        $this->check('Pedido: crear despacho', $viewOrder()->mountAction('ship'));
        $this->check('Pedido: crear despacho de más', $viewOrder()->callAction('ship', data: ['items' => ['i'.$order->items->first()->id => 99]]));
        $viewOrder()->callAction('ship', data: ['items' => ['i'.$order->items->first()->id => 1], 'packages' => 1]);
        $shipment = $order->shipments()->firstOrFail();
        $viewShipment = fn () => Livewire::test(ViewShipment::class, ['record' => $shipment->getRouteKey()]);
        $this->check('Despacho: cambiar estado', $viewShipment()->mountAction('status'));
        $this->check('Despacho: cambiar estado (vacío)', $viewShipment()->callAction('status', data: ['status' => null]));
        $this->check('Despacho: entregar sin despachar', $viewShipment()->callAction('status', data: ['status' => 'delivered']));
        $this->check('Despacho: seguimiento', $viewShipment()->mountAction('tracking'));
        $this->check('Despacho: incidencia (vacía)', $viewShipment()->callAction('incident', data: []));
        $viewShipment()->callAction('incident', data: ['type' => 'delay', 'description' => 'Atraso']);
        $this->check('Incidencias: resolver sin solución', Livewire::test(ManageShippingIncidents::class)
            ->callTableAction('manage', ShippingIncident::query()->firstOrFail(), data: ['status' => 'resolved', 'resolution' => null]));
        $this->check('Pagos: credenciales de prueba', Livewire::test(PaymentSettingsPage::class)->mountAction('webpayTestCredentials'));
        $this->check('Pagos: probar Webpay', Livewire::test(PaymentSettingsPage::class)->mountAction('webpayVerify'));
        $this->check('Pagos: código de comercio inválido', Livewire::test(PaymentSettingsPage::class)->fillForm(['webpay_commerce_code' => 'abc'])->call('save'));
        $this->check('Pagos (vacío)', Livewire::test(PaymentSettingsPage::class)->fillForm(['bank_transfer_enabled' => true, 'bank_transfer_hours' => null])->call('save'));

        // Fase 8: notas, devoluciones y correos
        $this->check('Pedido: agregar nota', $viewOrder()->mountAction('note'));
        $this->check('Pedido: agregar nota (vacía)', $viewOrder()->callAction('note', data: []));
        $this->check('Pedido: registrar devolución', $viewOrder()->mountAction('return'));
        $this->check('Pedido: registrar devolución (vacía)', $viewOrder()->callAction('return', data: ['type' => null, 'reason' => null]));
        $returns = app(ReturnService::class);
        $return = $returns->request($order->fresh(), [$order->items->first()->id => 1], 'return', 'defective', actor: 'user');
        $viewReturn = fn () => Livewire::test(ViewReturn::class, ['record' => $return->getRouteKey()]);
        $this->check('Devolución: ficha', $viewReturn());
        $this->check('Devolución: tomar para revisar', $viewReturn()->mountAction('review'));
        $this->check('Devolución: aprobar (vacío)', $viewReturn()->callAction('approve', data: ['instructions' => null]));
        $this->check('Devolución: rechazar (vacío)', $viewReturn()->callAction('reject', data: []));
        $this->check('Devolución: subir respaldo', $viewReturn()->mountAction('evidence'));
        $this->check('Devolución: anular', $viewReturn()->mountAction('cancel'));
        $returns->approve($return, 'Envíala a la tienda.');
        $item = 'i'.$return->items()->value('id');
        $this->check('Devolución: recibir', $viewReturn()->mountAction('receive'));
        $this->check('Devolución: recibir sin condición', $viewReturn()->callAction('receive', data: ['conditions' => [$item => ['condition' => null]]]));
        $returns->receive($return->fresh(), [$return->items()->value('id') => ['condition' => 'new', 'restock' => false]]);
        $this->check('Devolución: resolver', $viewReturn()->mountAction('process'));
        $this->check('Devolución: resolver (vacío)', $viewReturn()->callAction('process', data: ['refund_amount' => null]));
        $this->check('Devolución: reembolso negativo', $viewReturn()->callAction('process', data: ['refund_amount' => -5]));
        $this->check('Configuración de devoluciones (vacía)', Livewire::test(ReturnSettingsPage::class)->fillForm(['days' => null])->call('save'));
        EmailTemplate::syncDefaults();
        $template = EmailTemplate::query()->where('key', 'payment_confirmed')->firstOrFail();
        $editTemplate = fn () => Livewire::test(EditEmailTemplate::class, ['record' => $template->getRouteKey()]);
        $this->check('Plantilla: editar (vacía)', $editTemplate()->fillForm(['subject' => '', 'heading' => ''])->call('save'));
        $this->check('Plantilla: vista previa', $editTemplate()->mountAction('preview'));
        $this->check('Plantilla: enviarme una prueba', $editTemplate()->mountAction('test'));
        $this->check('Plantilla: prueba enviada', $editTemplate()->callAction('test')->assertNotified('Prueba enviada a '.$admin->email));
        $this->check('Plantilla: volver al original', $editTemplate()->mountAction('restore'));
        $this->check('Historial de correos', Livewire::test(ManageEmailLogs::class));
        $sent = EmailLog::query()->whereNotNull('mailable')->where('status', 'sent')->latest('id')->firstOrFail();
        $this->check('Historial: reenviar', Livewire::test(ManageEmailLogs::class)->mountTableAction('resend', $sent));
        $broken = EmailLog::query()->create(['template_key' => 'order_placed', 'to' => 'ana@correo.cl', 'status' => 'failed', 'attempts' => 3, 'mailable' => 'dañado']);
        $this->check('Historial: reenviar sin contenido', Livewire::test(ManageEmailLogs::class)->callTableAction('resend', $broken));

        $this->assertSame([], array_values(array_unique($this->problems)),
            "Texto en inglés en modales o validaciones:\n".implode("\n", array_unique($this->problems)));
    }

    public function test_content_modals_and_validation_messages_are_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $pages = app(PageService::class);
        $page = $pages->create(['title' => 'Nosotros', 'slug' => 'nosotros'], [['type' => 'rich_text', 'data' => ['body' => 'Uno']]]);
        $pages->publish($page);
        $pages->saveVersion($page, [['type' => 'rich_text', 'data' => ['body' => 'Dos']]]);
        $draft = $pages->create(['title' => 'Vacía', 'slug' => 'vacia']);
        $image = Media::query()->create(['disk' => 'public', 'path' => 'contenido/a.jpg', 'mime' => 'image/jpeg', 'size' => 10, 'width' => 10, 'height' => 10, 'checksum' => str_repeat('a', 64)]);

        $this->check('Páginas: listado', Livewire::test(ListPages::class));
        $this->check('Páginas: crear portada editable', Livewire::test(ListPages::class)->mountAction('homeFromCurrent'));
        $this->check('Páginas: nueva (vacía)', Livewire::test(CreatePage::class)->fillForm(['title' => '', 'type' => null])->call('create'));
        $this->check('Páginas: nueva con dirección reservada', Livewire::test(CreatePage::class)->fillForm(['title' => 'Carro', 'type' => 'institutional', 'slug' => 'carrito'])->call('create'));
        $edit = fn ($record = null) => Livewire::test(EditPage::class, ['record' => ($record ?? $page)->getRouteKey()]);
        $this->check('Página: editar', $edit());
        $this->check('Página: bloque sin titular', $edit()->fillForm(['blocks' => [['type' => 'hero', 'data' => ['title' => '']]]])->call('save'));
        $this->check('Página: enlace inválido', $edit()->fillForm(['blocks' => [['type' => 'hero', 'data' => ['title' => 'Hola', 'button_link' => 'javascript:alert(1)']]]])->call('save'));
        $this->check('Página: publicar', $edit()->mountAction('publish'));
        $this->check('Página: publicar sin bloques', $edit($draft)->callAction('publish'));
        $this->check('Página: despublicar', $edit()->mountAction('unpublish'));
        $versions = fn () => Livewire::test(VersionsRelationManager::class, ['ownerRecord' => $page, 'pageClass' => EditPage::class]);
        $first = $page->versions()->where('number', 1)->firstOrFail();
        $this->check('Versiones: listado', $versions());
        $this->check('Versiones: restaurar', $versions()->mountTableAction('restore', $first));
        $this->check('Versiones: publicar', $versions()->mountTableAction('publishVersion', $first));

        $menu = Menu::for('header');
        $this->check('Menús: listado', Livewire::test(ListMenus::class));
        $this->check('Menú: enlace vacío', Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->fillForm(['items' => [['label' => '', 'link_type' => 'url', 'url' => 'nada']]])->call('save'));

        $this->check('Biblioteca: listado', Livewire::test(ManageMedia::class));
        $this->check('Biblioteca: subir', Livewire::test(ManageMedia::class)->mountAction('upload'));
        $this->check('Biblioteca: subir (vacío)', Livewire::test(ManageMedia::class)->callAction('upload', data: []));
        $this->check('Biblioteca: editar', Livewire::test(ManageMedia::class)->mountTableAction('edit', $image));
        $this->check('Biblioteca: dónde se usa', Livewire::test(ManageMedia::class)->mountTableAction('usage', $image));
        $this->check('Biblioteca: eliminar', Livewire::test(ManageMedia::class)->mountTableAction('delete', $image));
        $this->check('Carpetas: nueva (vacía)', Livewire::test(ManageMediaFolders::class)->callAction('create', data: []));

        $this->check('Temas', Livewire::test(ThemesPage::class));
        $this->check('Temas: usar', Livewire::test(ThemesPage::class)->mountAction('activate', ['theme' => Theme::query()->where('code', 'lujo')->value('id')]));
        $this->check('Temas: contraste insuficiente', Livewire::test(ThemesPage::class)->fillForm(['accent' => '#ffffff', 'accent-fg' => '#fefefe'])->call('save'));

        $this->assertSame([], array_values(array_unique($this->problems)),
            "Texto en inglés en modales o validaciones:\n".implode("\n", array_unique($this->problems)));
    }

    public function test_marketing_modals_and_validation_messages_are_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $coupon = Coupon::query()->create(['code' => 'VERANO10', 'name' => 'Verano', 'type' => 'percent', 'value' => 10]);
        $promotion = Promotion::query()->create(['name' => '3x2', 'type' => 'buy_x_get_y', 'conditions' => ['buy_quantity' => 2], 'actions' => ['value' => 100, 'get_quantity' => 1]]);
        $subscriber = NewsletterSubscriber::query()->create(['email' => 'ana@correo.cl', 'source' => 'footer', 'status' => 'pending', 'confirm_sent_at' => now()]);
        $campaign = Campaign::query()->create(['name' => 'Cyber', 'subject' => 'Ofertas', 'heading' => 'Cyber', 'body' => 'Hola']);
        $redirect = app(RedirectService::class)->save(null, '/tienda/a', '/catalogo');

        $this->check('Cupones: nuevo', Livewire::test(ManageCoupons::class)->mountAction('create'));
        $this->check('Cupones: nuevo (vacío)', Livewire::test(ManageCoupons::class)->callAction('create', data: ['type' => 'percent']));
        $this->check('Cupones: código con espacios y fechas al revés', Livewire::test(ManageCoupons::class)->callAction('create', data: [
            'code' => 'a b', 'name' => 'x', 'type' => 'percent', 'value' => 500, 'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'ends_at' => now()->format('Y-m-d H:i'),
        ]));
        $this->check('Cupones: código repetido', Livewire::test(ManageCoupons::class)->callAction('create', data: ['code' => 'verano10', 'name' => 'x', 'type' => 'fixed', 'value' => 1000]));
        $this->check('Cupones: editar', Livewire::test(ManageCoupons::class)->mountTableAction('edit', $coupon));
        $this->check('Promociones: nueva (vacía)', Livewire::test(ManagePromotions::class)->callAction('create', data: ['type' => 'cart_amount']));
        $this->check('Promociones: editar 3x2', Livewire::test(ManagePromotions::class)->mountTableAction('edit', $promotion));
        $this->check('Boletín: agregar (vacío)', Livewire::test(ManageNewsletterSubscribers::class)->callAction('add', data: ['email' => 'no-es-correo']));
        $this->check('Boletín: dar de baja', Livewire::test(ManageNewsletterSubscribers::class)->mountTableAction('unsubscribe', $subscriber));
        $this->check('Boletín: reenviar', Livewire::test(ManageNewsletterSubscribers::class)->mountTableAction('resend', $subscriber));
        $this->check('Campañas: nueva (vacía)', Livewire::test(CreateCampaign::class)->fillForm(['audience' => null])->call('create'));
        $edit = fn () => Livewire::test(EditCampaign::class, ['record' => $campaign->getRouteKey()]);
        $this->check('Campaña: botón sin texto', $edit()->fillForm(['button_url' => '/catalogo', 'button_label' => ''])->call('save'));
        $this->check('Campaña: prueba', $edit()->mountAction('test'));
        $this->check('Campaña: programar', $edit()->mountAction('schedule'));
        $this->check('Campaña: programar sin suscritos', $edit()->callAction('schedule', data: ['now' => true]));
        $this->check('Campaña: programar en el pasado', $edit()->callAction('schedule', data: ['now' => false, 'at' => now()->subDay()->format('Y-m-d H:i')]));
        $this->check('Automatizaciones: editar', Livewire::test(ManageAutomations::class)->mountTableAction('edit', Automation::query()->first()));
        $this->check('Automatizaciones: activar', Livewire::test(ManageAutomations::class)->mountTableAction('toggle', Automation::query()->first()));
        $this->check('WhatsApp', Livewire::test(WhatsAppSettingsPage::class));
        $this->check('WhatsApp: número inválido', Livewire::test(WhatsAppSettingsPage::class)->fillForm(['enabled' => true, 'phone' => 'abc'])->call('save'));
        $this->check('Blog: nuevo (vacío)', Livewire::test(CreateBlogPost::class)->fillForm(['status' => null])->call('create'));
        $this->check('Blog: categorías', Livewire::test(ManageBlogCategories::class)->callAction('create', data: []));
        $this->check('Redirecciones: nueva protegida', Livewire::test(ManageRedirects::class)->callAction('create', data: ['from_path' => '/api/x', 'to_path' => 'ftp://x', 'code' => 301]));
        $this->check('Redirecciones: ciclo', Livewire::test(ManageRedirects::class)->callAction('create', data: ['from_path' => '/catalogo', 'to_path' => '/tienda/a', 'code' => 301]));
        $this->check('Redirecciones: eliminar', Livewire::test(ManageRedirects::class)->mountTableAction('delete', $redirect));

        $this->assertSame([], array_values(array_unique($this->problems)),
            "Texto en inglés en modales o validaciones:\n".implode("\n", array_unique($this->problems)));
    }
}
