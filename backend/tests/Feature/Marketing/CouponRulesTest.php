<?php

namespace Tests\Feature\Marketing;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\CouponRedemption;
use App\Domain\Marketing\Models\Promotion;
use App\Domain\Marketing\Services\DiscountEngine;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

/** Fase 10 (criterio de aceptación): reglas de cupón y promociones cubiertas por pruebas, de punta a punta por la API. */
class CouponRulesTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareStore();
        $polera = $this->stockedProduct('Polera', 10000, 50);
        $taza = $this->stockedProduct('Taza', 5000, 50);
        $this->token = $this->addToCart($polera->variants()->first()->sku, 2)->json('token');
        $this->addToCart($taza->variants()->first()->sku, 1, $this->token);
    }

    private function apply(string $code)
    {
        return $this->flushHeaders()->withHeaders(['X-Cart-Token' => $this->token])->postJson(self::API.'/cart/coupon', ['code' => $code]);
    }

    private function cart(): array
    {
        return $this->flushHeaders()->withHeaders(['X-Cart-Token' => $this->token])->getJson(self::API.'/cart')->json('data');
    }

    private function coupon(array $attrs = []): Coupon
    {
        return Coupon::query()->create(['code' => 'flux10', 'name' => 'Bienvenida', 'type' => 'percent', 'value' => 10, ...$attrs]);
    }

    public function test_percent_coupon_is_split_across_lines_and_vat_is_on_the_discounted_price(): void
    {
        $this->coupon();
        $data = $this->apply(' flux 10 ')->assertOk()->json('data');
        $this->assertSame(25000, $data['subtotal']);
        $this->assertSame(2500, $data['discount_total']);
        $this->assertSame(22500, $data['total']);
        $this->assertSame(['code' => 'FLUX10', 'name' => 'Bienvenida', 'amount' => 2500, 'free_shipping' => false], $data['discounts']['coupon']);
        $this->assertSame([2000, 500], array_column($data['items'], 'discount'));
        $this->assertSame((int) round(22500 - 22500 / 1.19), $data['tax_total']);
        $this->assertArrayNotHasKey('unit_cost', $data['items'][0]);
        $this->assertArrayNotHasKey('category_ids', $data['items'][0]);

        $order = $this->checkout($this->token, $this->checkoutData(['expected_total' => 22500 + 3990]))->assertCreated();
        $order = Order::query()->where('number', $order->json('data.number'))->firstOrFail();
        $this->assertSame(2500, $order->discount_total);
        $this->assertSame(22500 + 3990, $order->grand_total, 'grand_total = subtotal - descuento + envío');
        $this->assertSame([2000, 500], $order->items->pluck('discount_amount')->all());
        $this->assertSame([['source' => 'coupon', 'label' => 'Cupón FLUX10', 'amount' => 2500]], $order->adjustments->map->only('source', 'label', 'amount')->all());
        $this->assertSame(1, CouponRedemption::query()->count());
        $this->assertSame(9000, $order->items->first()->paidFor(1), 'Una devolución reembolsa lo pagado, con el descuento');
    }

    public function test_fixed_coupon_with_minimum_and_cap(): void
    {
        $this->coupon(['code' => 'MIL', 'type' => 'fixed', 'value' => 30000]);
        $this->assertSame(25000, $this->apply('MIL')->json('data.discount_total'), 'Nunca más que el total de los productos');

        $this->coupon(['code' => 'MINIMO', 'type' => 'fixed', 'value' => 1000, 'min_subtotal' => 30000]);
        $this->apply('MINIMO')->assertStatus(422)->assertJsonPath('errors.code.0', 'Este cupón requiere una compra mínima de $30.000.');

        $this->coupon(['code' => 'TOPE', 'value' => 50, 'max_discount' => 5000]);
        $this->assertSame(5000, $this->apply('TOPE')->json('data.discount_total'));
    }

    public static function invalidCoupons(): array
    {
        return [
            'no existe' => [[], 'OTRO', 'El cupón no existe o ya no está vigente.'],
            'inactivo' => [['status' => 'inactive'], 'FLUX10', 'El cupón no existe o ya no está vigente.'],
            'aún no empieza' => [['starts_at' => now()->addDay()], 'FLUX10', 'Este cupón todavía no está vigente.'],
            'vencido' => [['ends_at' => now()->subMinute()], 'FLUX10', 'Este cupón venció.'],
        ];
    }

    #[DataProvider('invalidCoupons')]
    public function test_invalid_coupons_are_rejected_with_a_reason(array $attrs, string $code, string $message): void
    {
        $this->coupon($attrs);
        $this->apply($code)->assertStatus(422)->assertJsonPath('errors.code.0', $message);
        $this->assertNull($this->cart()['discounts']['coupon'], 'El carrito queda como estaba');
    }

    public function test_targets_include_categories_with_children_brands_and_exclusions(): void
    {
        $ropa = Category::query()->create(['name' => 'Ropa', 'slug' => 'ropa']);
        $poleras = Category::query()->create(['name' => 'Poleras', 'slug' => 'poleras', 'parent_id' => $ropa->id]);
        $polera = Product::query()->where('name', 'Polera')->first();
        $polera->update(['primary_category_id' => $poleras->id]);

        $coupon = $this->coupon();
        $coupon->targets()->create(['target_type' => 'category', 'target_id' => $ropa->id, 'mode' => 'include']);
        $this->assertSame(2000, $this->apply('FLUX10')->json('data.discount_total'), 'Solo las poleras (subcategoría de Ropa)');

        $coupon->targets()->create(['target_type' => 'product', 'target_id' => $polera->id, 'mode' => 'exclude']);
        $this->assertSame('Ningún producto de tu carrito participa de este cupón.', $this->cart()['discounts']['coupon_error']);

        $brand = Brand::query()->create(['name' => 'Loza', 'slug' => 'loza']);
        Product::query()->where('name', 'Taza')->update(['brand_id' => $brand->id]);
        $coupon->targets()->create(['target_type' => 'brand', 'target_id' => $brand->id, 'mode' => 'include']);
        $this->assertSame(500, $this->cart()['discount_total']);
    }

    public function test_usage_limits_per_coupon_and_per_customer_and_first_purchase(): void
    {
        $coupon = $this->coupon(['usage_limit' => 1]);
        $this->apply('FLUX10')->assertOk();
        $this->checkout($this->token, $this->checkoutData())->assertCreated();

        // Otro cliente: límite total alcanzado
        $sku = ProductVariant::query()->first()->sku;
        $this->token = $this->addToCart($sku, 1)->json('token');
        $this->apply('FLUX10')->assertStatus(422)->assertJsonPath('errors.code.0', 'Este cupón ya se usó el máximo de veces.');

        // Cancelar el pedido libera el uso
        $order = Order::query()->firstOrFail();
        app(OrderPayments::class)->cancel($order, 'Prueba', 'user');
        $this->apply('FLUX10')->assertOk();

        // Por cliente: el mismo correo no puede usarlo dos veces
        $coupon->update(['usage_limit' => null, 'per_customer_limit' => 1]);
        $this->checkout($this->token, $this->checkoutData(), 'clave-2')->assertCreated();
        $this->token = $this->addToCart($sku, 1)->json('token');
        $this->apply('FLUX10')->assertOk();
        $this->checkout($this->token, $this->checkoutData(['email' => 'ANA@correo.cl']), 'clave-3')
            ->assertStatus(409)->assertJsonPath('code', 'coupon_invalid');
        $this->checkout($this->token, $this->checkoutData(['email' => 'otra@correo.cl']), 'clave-4')->assertCreated();

        // Solo primera compra
        Coupon::query()->create(['code' => 'PRIMERA', 'name' => 'Primera', 'type' => 'fixed', 'value' => 1000, 'first_purchase_only' => true]);
        $this->token = $this->addToCart($sku, 1)->json('token');
        $this->apply('PRIMERA')->assertOk();
        $this->checkout($this->token, $this->checkoutData(['email' => 'otra@correo.cl']), 'clave-5')
            ->assertStatus(409)->assertJsonPath('code', 'coupon_invalid')->assertJsonPath('detail', 'Este cupón es solo para la primera compra. Quítalo del carrito para continuar.');
        $this->checkout($this->token, $this->checkoutData(['email' => 'nueva@correo.cl']), 'clave-6')->assertCreated();
    }

    public function test_free_shipping_coupon(): void
    {
        $this->coupon(['code' => 'ENVIO', 'type' => 'free_shipping', 'value' => 0]);
        $this->apply('ENVIO')->assertOk()->assertJsonPath('data.discounts.free_shipping', true);
        $options = $this->flushHeaders()->withHeaders(['X-Cart-Token' => $this->token])->getJson(self::API.'/checkout/options?region_code=CL-RM')->json('data');
        $this->assertSame(0, $options['shipping_methods'][0]['amount']);
        $this->assertSame(3990, $options['shipping_methods'][0]['original_amount']);

        $number = $this->checkout($this->token, $this->checkoutData(['expected_total' => 25000]))->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertSame(0, $order->shipping_total);
        $this->assertSame(0, $order->discount_total);
        $this->assertSame(25000, $order->grand_total);
        $this->assertSame(['shipping', 3990], [$order->adjustments->first()->target, $order->adjustments->first()->amount]);
    }

    public function test_free_shipping_threshold_uses_the_total_after_discounts(): void
    {
        // El despacho es gratis desde $50.000: con 4 poleras (40.000) + taza (5.000) + 1 polera más = 55.000, con 20 % baja a 44.000
        $this->addToCart(ProductVariant::query()->first()->sku, 3, $this->token);
        $this->coupon(['code' => 'VEINTE', 'value' => 20]);
        $this->apply('VEINTE')->assertOk();
        $options = $this->flushHeaders()->withHeaders(['X-Cart-Token' => $this->token])->getJson(self::API.'/checkout/options?region_code=CL-RM')->json('data');
        $this->assertSame(55000, $options['subtotal']);
        $this->assertSame(44000, $options['total']);
        $this->assertSame(3990, $options['shipping_methods'][0]['amount']);
    }

    public function test_automatic_promotions_and_how_they_combine_with_coupons(): void
    {
        Promotion::query()->create(['name' => '3x2 en poleras', 'type' => 'buy_x_get_y', 'conditions' => ['buy_quantity' => 2, 'product_ids' => [Product::query()->where('name', 'Polera')->value('id')]], 'actions' => ['get_quantity' => 1, 'value' => 100]]);
        $this->addToCart(ProductVariant::query()->first()->sku, 1, $this->token); // 3 poleras
        $cart = $this->cart();
        $this->assertSame([['name' => '3x2 en poleras', 'amount' => 10000]], $cart['discounts']['promotions']);

        Promotion::query()->create(['name' => '5 % sobre $30.000', 'type' => 'cart_amount', 'conditions' => ['min_subtotal' => 30000], 'actions' => ['discount_type' => 'percent', 'value' => 5]]);
        $cart = $this->cart();
        $this->assertSame(10000 + 1250, $cart['discount_total'], 'La segunda se calcula sobre lo que queda (25.000)');

        // Cupón combinable: se suma, sobre el precio ya rebajado
        $this->coupon();
        $this->assertSame(11250 + 2375, $this->apply('FLUX10')->json('data.discount_total'));

        // Cupón no combinable que conviene menos: no se aplica y se explica
        $this->coupon(['code' => 'SOLO', 'value' => 10, 'stackable' => false]);
        $this->apply('SOLO')->assertStatus(422)->assertJsonPath('errors.code.0', 'Este cupón no se combina con la promoción vigente, que te conviene más.');

        // Cupón no combinable que conviene más: reemplaza a las promociones
        $this->coupon(['code' => 'MITAD', 'value' => 50, 'stackable' => false]);
        $data = $this->apply('MITAD')->json('data');
        $this->assertSame(17500, $data['discount_total']);
        $this->assertSame([], $data['discounts']['promotions']);
    }

    public function test_qty_and_collection_promotions_respect_dates_and_exclusivity(): void
    {
        $taza = Product::query()->where('name', 'Taza')->value('id');
        Promotion::query()->create(['name' => 'Futura', 'type' => 'collection', 'conditions' => ['product_ids' => [$taza]], 'actions' => ['value' => 50], 'starts_at' => now()->addDay()]);
        $this->assertSame(0, $this->cart()['discount_total']);

        Promotion::query()->create(['name' => '2 o más: 10 %', 'type' => 'qty_discount', 'conditions' => ['min_quantity' => 3], 'actions' => ['value' => 10], 'priority' => 1]);
        $this->assertSame(2500, $this->cart()['discount_total']);

        Promotion::query()->create(['name' => 'Tazas 20 % (exclusiva)', 'type' => 'collection', 'conditions' => ['product_ids' => [$taza]], 'actions' => ['value' => 20], 'stackable' => false, 'priority' => 5]);
        $this->assertSame([['name' => 'Tazas 20 % (exclusiva)', 'amount' => 1000]], $this->cart()['discounts']['promotions']);
    }

    public function test_allocation_never_exceeds_a_line_and_sums_exactly(): void
    {
        $this->assertSame([34, 33, 33], DiscountEngine::allocate(100, [100, 100, 100]));
        $this->assertSame([1, 0], DiscountEngine::allocate(1, [1, 1]));
        $this->assertSame([5, 3], DiscountEngine::allocate(50, [5, 3]));
        $this->assertSame(999, array_sum(DiscountEngine::allocate(999, [12345, 678, 9])));
    }
}
