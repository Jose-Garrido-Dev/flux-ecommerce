<?php

namespace Tests\Feature\Checkout;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\System\Services\SettingsRepository;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Catalog\CatalogBuilder;

/** Tienda lista para vender: transferencia configurada, despacho a domicilio y un producto con stock. */
trait CheckoutFixtures
{
    use CatalogBuilder;

    protected const API = '/api/storefront/v1';

    protected function prepareStore(): void
    {
        $this->seed(DatabaseSeeder::class);
        app(SettingsRepository::class)->putMany('payments', [
            'bank_transfer_enabled' => true, 'bank_name' => 'Banco de Prueba', 'account_type' => 'Cuenta corriente',
            'account_number' => '123456789', 'account_holder' => 'Tienda SpA', 'account_rut' => '76.123.456-0',
            'account_email' => 'pagos@tienda.test', 'bank_transfer_hours' => 48,
        ]);
        app(SettingsRepository::class)->putMany('business', ['store_name' => 'Tienda de Prueba', 'contact_email' => 'ventas@tienda.test']);
        ShippingMethod::query()->create(['code' => 'domicilio', 'name' => 'Despacho a domicilio', 'type' => 'flat', 'amount' => 3990, 'free_over' => 50000, 'estimated_days' => '2 a 4 días hábiles']);
    }

    protected function stockedProduct(string $name, int $price, int $stock, ?int $sale = null): Product
    {
        $product = $this->product($name, [['price' => $price, 'sale' => $sale]]);
        $variant = $product->variants()->first();
        $inventory = app(InventoryService::class);
        $inventory->item($variant)->update(['track_stock' => true]);
        if ($stock > 0) {
            $inventory->move($variant, Warehouse::default(), MovementType::PurchaseIn, $stock, 'Compra');
        }

        return $product;
    }

    protected function addToCart(string $sku, int $quantity, ?string $token = null): TestResponse
    {
        return $this->flushHeaders()->withHeaders(array_filter(['X-Cart-Token' => $token]))
            ->postJson(self::API.'/cart/items', ['sku' => $sku, 'quantity' => $quantity]);
    }

    protected function checkoutData(array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'Ana@Correo.cl', 'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '+56 9 1234 5678',
            'shipping_method' => 'domicilio', 'payment_method' => 'bank_transfer',
            'address' => [
                'recipient' => 'Ana Pérez', 'phone' => '+56 9 1234 5678', 'street' => 'Av. Providencia', 'number' => '1234',
                'apartment' => 'Depto 501', 'region_code' => 'CL-RM', 'commune' => 'Providencia', 'reference' => 'Frente al parque',
            ],
            'accepts_terms' => true,
        ], $overrides);
    }

    protected function checkout(string $cartToken, array $data, string $key = 'clave-1', ?string $bearer = null): TestResponse
    {
        return $this->flushHeaders()->withHeaders(array_filter([
            'X-Cart-Token' => $cartToken, 'Idempotency-Key' => $key, 'Authorization' => $bearer ? "Bearer $bearer" : null,
        ]))->postJson(self::API.'/checkout', $data);
    }
}
