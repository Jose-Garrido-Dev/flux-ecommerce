<?php

namespace Tests\Feature\Language;

use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Customers\Models\Customer;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\PlaceOrder;
use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Services\RefundService;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\System\Services\SettingsRepository;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Catalog\CatalogBuilder;
use Tests\TestCase;

/**
 * Regla del proyecto: todo lo que ve una persona está en español.
 * Recorre cada pantalla del panel con registros reales y falla si aparece texto visible en inglés
 * (etiquetas generadas a partir del nombre del campo, textos por defecto sin traducir, etc.).
 */
class SpanishOnlyAdminTest extends TestCase
{
    use CatalogBuilder, RefreshDatabase;

    public function test_every_admin_page_is_in_spanish(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::factory()->create(['status' => 'active', 'name' => 'Ana Pérez']);
        $admin->assignRole('super-admin');
        $this->actingAs($admin);

        $this->attribute('color', ['negro', 'blanco']);
        $product = $this->product('Polera', [['price' => 9990, 'options' => ['color' => 'negro']], ['price' => 9990, 'options' => ['color' => 'blanco']]]);
        $category = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $brand = Brand::query()->create(['name' => 'Marca', 'slug' => 'marca']);
        // Inventario con datos: segunda bodega, movimientos, transferencia y reserva
        $inventory = app(InventoryService::class);
        $variant = $product->variants()->first();
        $main = Warehouse::default();
        $store = Warehouse::query()->create(['name' => 'Tienda centro', 'code' => 'CENTRO', 'priority' => 10]);
        $inventory->item($variant)->update(['track_stock' => true, 'min_stock' => 3]);
        $inventory->move($variant, $main, MovementType::PurchaseIn, 10, 'Compra', 'document', 'Factura 1');
        $transfer = $inventory->transfer($main, $store, [['variant_id' => $variant->id, 'quantity' => 2]], 'Reposición');
        $inventory->reserve($variant, 1, 'cart', 'carrito-prueba');
        // Ventas: un pedido de invitado, un cliente registrado y un método de envío
        app(SettingsRepository::class)->putMany('payments', [
            'bank_transfer_enabled' => true, 'bank_name' => 'Banco', 'account_type' => 'Cuenta corriente', 'account_number' => '1',
            'account_holder' => 'Tienda', 'account_rut' => '76.123.456-0',
        ]);
        ShippingMethod::query()->create(['code' => 'domicilio', 'name' => 'Despacho', 'type' => 'flat', 'amount' => 3990]);
        $customer = Customer::query()->create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'email' => 'ana@correo.cl', 'password' => 'clave1234']);
        $customer->addresses()->create(['recipient' => 'Ana', 'phone' => '912345678', 'street' => 'Los Leones', 'number' => '1', 'region_id' => Region::query()->first()->id, 'commune' => 'Providencia', 'is_default' => true]);
        [$cart] = app(CartService::class)->create($customer);
        app(CartService::class)->setQuantity($cart, $variant->sku, 1);
        $order = app(PlaceOrder::class)->handle($cart, [
            'email' => 'ana@correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '912345678',
            'shipping_method' => 'domicilio', 'payment_method' => 'bank_transfer', 'document_type' => 'factura',
            'billing_rut' => '76.123.456-0', 'billing_name' => 'Empresa SpA', 'billing_activity' => 'Comercio',
            'address' => ['recipient' => 'Ana', 'phone' => '912345678', 'street' => 'Los Leones', 'number' => '1', 'region_code' => 'CL-RM', 'commune' => 'Providencia'],
        ], $customer, 'clave');
        app(OrderPayments::class)->confirm($order, $order->grand_total, 'OP-1');
        app(RefundService::class)->refund($order, 1000, 'Descuento por demora');
        // Logística: zona con tarifa, despacho con seguimiento e incidencia
        $zone = ShippingZone::query()->create(['name' => 'Santiago', 'region_ids' => [Region::query()->first()->id]]);
        $table = ShippingMethod::query()->create(['code' => 'courier', 'name' => 'Courier', 'type' => 'table', 'amount' => 0, 'carrier_code' => 'starken']);
        $table->rates()->create(['zone_id' => $zone->id, 'min_weight_g' => 0, 'max_weight_g' => 5000, 'amount' => 4990]);
        $shipments = app(ShipmentService::class);
        $shipment = $shipments->create($order, [], ['carrier_code' => 'starken', 'tracking_number' => '123456']);
        $shipments->updateStatus($shipment, 'shipped', 'Retirado por el courier');
        $shipments->openIncident($shipment, 'delay', 'El courier informa atraso');
        // Webpay verificada (pantalla de configuración con estado)
        $webpay = PaymentGateway::for('webpay_plus');
        $webpay->credentials = ['commerce_code' => '597055555532', 'api_key' => 'x'];
        $webpay->save();
        $webpay->update(['enabled' => true, 'verified_at' => now(), 'verified_environment' => 'integration', 'verified_fingerprint' => $webpay->fingerprint()]);
        $records = [
            'orders' => $order, 'customers' => $customer, 'shipments' => $shipment,
            'stock-transfers' => $transfer,
            'products' => $product, 'categories' => $category, 'brands' => $brand,
            'attributes' => Attribute::query()->first(), 'users' => $admin, 'regions' => Region::query()->first(),
        ];

        $urls = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'admin') || ! in_array('GET', $route->methods(), true)
                || str_contains($uri, 'logout') || str_contains($uri, 'login') || str_contains($uri, 'password')) {
                continue;
            }
            if (str_contains($uri, '{record}')) {
                $segment = explode('/', $uri)[1] ?? '';
                if (! isset($records[$segment])) {
                    continue;
                }
                $uri = str_replace('{record}', (string) $records[$segment]->getRouteKey(), $uri);
            }
            if (str_contains($uri, '{')) {
                continue;
            }
            $urls[] = '/'.$uri;
        }
        // Pestañas de relaciones del producto
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=0';
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=1';
        $urls[] = '/admin/products/'.$product->getRouteKey().'/edit?relation=2';

        $problems = [];
        foreach (array_unique($urls) as $url) {
            $response = $this->get($url);
            if ($response->status() >= 500) {
                $problems[] = "$url → error {$response->status()}: ".$response->exception?->getMessage();

                continue;
            }
            if ($response->status() !== 200) {
                continue;
            }
            foreach (self::englishFragments($response->getContent()) as $fragment) {
                $problems[] = "$url → \"$fragment\"";
            }
        }

        $this->assertSame([], array_values(array_unique($problems)), "Texto en inglés visible en el panel:\n".implode("\n", array_unique($problems)));
    }

    public function test_guest_login_page_is_in_spanish(): void
    {
        $this->assertSame([], self::englishFragments($this->get('/admin/login')->getContent()));
    }

    /** @return list<string> fragmentos de texto visible que contienen palabras en inglés */
    public static function englishFragments(string $html): array
    {
        // Solo texto visible: se quitan scripts, estilos, svg y atributos técnicos
        $html = preg_replace('#<(script|style|svg|template)\b[^>]*>.*?</\1>#si', ' ', $html);
        $texts = [];
        preg_match_all('/>([^<>]+)</', $html, $m);
        $texts = $m[1];
        // Atributos que se leen en pantalla o con lector de pantalla
        preg_match_all('/\s(?:placeholder|title|aria-label|alt)="([^"]*)"/i', $html, $attrs);
        $texts = array_merge($texts, $attrs[1]);

        // Lista compartida con la revisión de la tienda (packages/qa/palabras-ingles.json)
        $list = json_decode(file_get_contents(base_path('../packages/qa/palabras-ingles.json')), true);
        $english = array_flip(array_diff($list['ingles'], $list['permitidas']));
        $found = [];
        foreach ($texts as $text) {
            $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
            if ($text === '' || str_contains($text, '{{') || str_contains($text, '$wire') || str_contains($text, '=>')) {
                continue;
            }
            foreach (preg_split('/[^\p{L}]+/u', mb_strtolower($text)) as $word) {
                if (isset($english[$word])) {
                    $found[] = mb_substr(preg_replace('/\s+/', ' ', $text), 0, 80);
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }
}
