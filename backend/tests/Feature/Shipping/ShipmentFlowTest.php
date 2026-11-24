<?php

namespace Tests\Feature\Shipping;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\OrderTokens;
use App\Domain\Shipping\Mail\ShipmentMail;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Services\ShipmentService;
use App\Domain\Shipping\Services\ShipmentTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Checkout\CheckoutFixtures;
use Tests\TestCase;

/** Fase 7: tarifa por zona en el checkout, despachos parciales, estados, correos y seguimiento público sin datos personales. */
class ShipmentFlowTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private ShipmentService $shipments;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->prepareStore();
        $this->shipments = app(ShipmentService::class);
        Courier::for('starken')->update(['enabled' => true, 'tracking_url_template' => 'https://courier.example/seguimiento?n={numero}']);
    }

    /** Pedido pagado con 2 tazas y 1 polera, despachado a Providencia con tarifa por zona. */
    private function paidOrder(string $method = 'courier'): Order
    {
        $this->stockedProduct('Taza', 10000, 5);
        $this->stockedProduct('Polera', 12000, 5);
        $token = $this->addToCart('TAZA-0', 2)->json('token');
        $this->addToCart('POLERA-0', 1, $token);
        $number = $this->checkout($token, $this->checkoutData(['shipping_method' => $method]))->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        app(OrderPayments::class)->confirm($order, $order->grand_total);

        return $order->fresh();
    }

    private function tableMethod(): ShippingMethod
    {
        $zone = ShippingZone::query()->create(['name' => 'Santiago', 'region_ids' => [Region::query()->where('code', 'CL-RM')->value('id')], 'excluded_communes' => ['Lo Barnechea']]);
        $method = ShippingMethod::query()->create(['code' => 'courier', 'name' => 'Starken a domicilio', 'type' => 'table', 'amount' => 0, 'carrier_code' => 'starken', 'volumetric_factor' => 4000]);
        $method->rates()->createMany([
            ['zone_id' => $zone->id, 'min_weight_g' => 0, 'max_weight_g' => 2000, 'amount' => 2990, 'estimated_days' => '1 a 2 días hábiles'],
            ['zone_id' => $zone->id, 'min_weight_g' => 2001, 'max_weight_g' => 10000, 'amount' => 5990],
        ]);

        return $method;
    }

    public function test_checkout_charges_the_zone_rate_for_the_chargeable_weight_and_stores_it(): void
    {
        $this->tableMethod();
        $this->stockedProduct('Taza', 10000, 5)->variants()->first()->update(['weight_g' => 400]);
        $token = $this->addToCart('TAZA-0', 3)->json('token');

        $options = fn (string $q) => $this->flushHeaders()->withHeaders(['X-Cart-Token' => $token])->getJson(self::API."/checkout/options?$q")->assertOk()->json('data.shipping_methods');
        $courier = collect($options('region_code=CL-RM&commune=Providencia'))->firstWhere('code', 'courier');
        $this->assertSame(2990, $courier['amount']);
        $this->assertSame('Starken', $courier['carrier_name']);
        $this->assertSame('Santiago', $courier['zone_name']);
        $this->assertSame('1 a 2 días hábiles', $courier['estimated_days']);
        $this->assertArrayNotHasKey('method_id', $courier);
        $this->assertNull(collect($options('region_code=CL-RM&commune=Lo%20Barnechea'))->firstWhere('code', 'courier'), 'Comuna excluida: no se ofrece');

        // 3 × 400 g reales, pero cada caja de 20 × 20 × 20 cm pesa 2 kg volumétricos: 6 kg → segundo tramo
        Order::query()->delete();
        ProductVariant::query()->where('sku', 'TAZA-0')->update(['length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200]);
        $data = $this->checkout($token, $this->checkoutData(['shipping_method' => 'courier', 'expected_total' => 30000 + 5990]))->assertCreated()->json('data');
        $line = Order::query()->where('number', $data['number'])->firstOrFail()->shippingLine;
        $this->assertSame(5990, $line->amount);
        $this->assertSame('starken', $line->carrier_code);
        $this->assertSame('Santiago', $line->zone_name);
        $this->assertSame(6000, $line->chargeable_weight_g);
    }

    public function test_checkout_rejects_a_method_that_does_not_serve_the_commune(): void
    {
        $this->tableMethod();
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->addToCart('TAZA-0', 1)->json('token');
        $this->checkout($token, $this->checkoutData(['shipping_method' => 'courier', 'address' => ['commune' => 'Lo Barnechea']]))
            ->assertStatus(422)->assertJsonPath('code', 'shipping_unavailable');
    }

    public function test_partial_shipments_status_flow_emails_and_order_completion(): void
    {
        $this->tableMethod();
        $order = $this->paidOrder();
        $this->assertSame('processing', $order->status);
        [$taza, $polera] = [$order->items->firstWhere('sku', 'TAZA-0'), $order->items->firstWhere('sku', 'POLERA-0')];

        // Primer despacho: 1 de 2 tazas, con el courier del método elegido
        $first = $this->shipments->create($order, [$taza->id => 1], ['tracking_number' => 'ABC123']);
        $this->assertSame('Starken', $first->carrier_name);
        $this->assertSame('https://courier.example/seguimiento?n=ABC123', $this->shipments->trackingUrl($first));
        $this->assertSame([$taza->id => 1, $polera->id => 1], $this->shipments->remaining($order));

        // No se puede despachar más de lo que falta
        $this->assertThrows(fn () => $this->shipments->create($order, [$taza->id => 2]), ValidationException::class, 'Las cantidades superan lo que falta por despachar.');

        $this->shipments->updateStatus($first, 'shipped', 'Retirado por el courier');
        $this->assertSame('partially_shipped', $order->fresh()->fulfillment_status);
        Mail::assertQueued(ShipmentMail::class, fn (ShipmentMail $m) => $m->kind === 'shipped' && $m->hasTo('ana@correo.cl'));

        // Transiciones no permitidas
        $this->assertThrows(fn () => $this->shipments->updateStatus($first, 'preparing'), ValidationException::class);
        $this->assertThrows(fn () => $this->shipments->updateStatus($first, 'ready_for_pickup'), ValidationException::class);

        // Evento del courier repetido: se aplica una sola vez
        $this->shipments->updateStatus($first, 'in_transit', 'En ruta', 'Santiago', source: 'courier', externalId: 'evt-1');
        $this->shipments->updateStatus($first, 'in_transit', 'En ruta', 'Santiago', source: 'courier', externalId: 'evt-1');
        $this->assertSame(1, $first->events()->where('external_event_id', 'evt-1')->count());

        // El resto (vacío = todo lo pendiente) y ambos entregados: el pedido queda completado
        $second = $this->shipments->create($order, []);
        $this->assertSame([$taza->id => 0, $polera->id => 0], $this->shipments->remaining($order));
        $this->shipments->updateStatus($second, 'shipped');
        $this->assertSame('shipped', $order->fresh()->fulfillment_status);
        $this->shipments->updateStatus($first, 'delivered');
        $this->assertSame('processing', $order->fresh()->status, 'Falta el segundo despacho');
        $this->shipments->updateStatus($second, 'delivered');
        $order->refresh();
        $this->assertSame('delivered', $order->fulfillment_status);
        $this->assertSame('completed', $order->status);
        $this->assertNotNull($first->fresh()->delivered_at);
        $this->assertTrue($first->fresh()->isFinal());
        $this->assertThrows(fn () => $this->shipments->setTracking($first->fresh(), 'X', null), ValidationException::class);
        Mail::assertQueued(ShipmentMail::class, fn (ShipmentMail $m) => $m->kind === 'delivered');
        $this->assertTrue($order->events()->where('type', 'completed')->exists());
    }

    public function test_only_paid_orders_in_preparation_can_be_shipped(): void
    {
        $this->tableMethod();
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->addToCart('TAZA-0', 1)->json('token');
        $number = $this->checkout($token, $this->checkoutData(['shipping_method' => 'courier']))->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertThrows(fn () => $this->shipments->create($order, []), ValidationException::class, 'Solo se despachan pedidos pagados que están en preparación.');
    }

    public function test_pickup_orders_go_ready_for_pickup_then_picked_up(): void
    {
        ShippingMethod::query()->updateOrCreate(['code' => 'retiro'], ['name' => 'Retiro en tienda', 'type' => 'pickup', 'amount' => 0, 'status' => 'active', 'pickup_address' => 'Av. Italia 100, Providencia']);
        $this->stockedProduct('Taza', 10000, 5);
        $token = $this->addToCart('TAZA-0', 1)->json('token');
        $data = $this->checkoutData(['shipping_method' => 'retiro']);
        unset($data['address']);
        $number = $this->checkout($token, $data)->assertCreated()->json('data.number');
        $order = Order::query()->where('number', $number)->firstOrFail();
        $this->assertSame('Av. Italia 100, Providencia', $order->shippingLine->pickup_address);
        app(OrderPayments::class)->confirm($order, $order->grand_total);

        $shipment = $this->shipments->create($order->fresh(), [], ['carrier_code' => 'starken']);
        $this->assertSame('Retiro en tienda', $shipment->carrier_name, 'Un retiro nunca va por courier');
        $this->assertThrows(fn () => $this->shipments->updateStatus($shipment, 'shipped'), ValidationException::class);
        $this->shipments->updateStatus($shipment, 'ready_for_pickup');
        $this->assertSame('ready_for_pickup', $order->fresh()->fulfillment_status);
        Mail::assertQueued(ShipmentMail::class, fn (ShipmentMail $m) => $m->kind === 'ready_for_pickup');
        $this->shipments->updateStatus($shipment, 'picked_up');
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_public_tracking_shows_progress_without_personal_data(): void
    {
        $this->tableMethod();
        $order = $this->paidOrder();
        $shipment = $this->shipments->create($order, [], ['tracking_number' => 'ABC123']);
        $this->shipments->updateStatus($shipment, 'shipped', 'Retirado por el courier', 'Santiago');
        $token = ShipmentTokens::for($shipment->uuid);

        $response = $this->getJson(self::API."/shipments/$token")->assertOk();
        $data = $response->json('data');
        $this->assertSame('shipped', $data['status']);
        $this->assertSame('Despachado', $data['status_label']);
        $this->assertSame('ABC123', $data['tracking_number']);
        $this->assertSame($order->number, $data['order_number']);
        $this->assertSame(['commune' => 'Providencia', 'region' => 'Metropolitana de Santiago'], $data['destination']);
        $this->assertCount(2, $data['events']);
        $this->assertArrayNotHasKey('follow_token', $data);
        $body = $response->getContent();
        foreach (['Ana', 'Pérez', 'correo.cl', 'Providencia 1234', '1234 5678', 'Depto 501', 'Frente al parque', $order->uuid, $shipment->uuid] as $private) {
            $this->assertStringNotContainsString($private, $body, "El seguimiento público no debe mostrar: $private");
        }

        // El pedido del cliente incluye el despacho con su enlace para compartir
        $orderToken = OrderTokens::for($order->uuid);
        $mine = $this->getJson(self::API."/orders/$orderToken")->assertOk()->json('data');
        $this->assertSame($token, $mine['shipments'][0]['follow_token']);
        $this->assertSame('shipped', $mine['fulfillment_status']);

        $this->getJson(self::API.'/shipments/'.str_repeat('a', 43))->assertNotFound();
        $shipment->forceFill(['status' => 'cancelled'])->save();
        $this->getJson(self::API."/shipments/$token")->assertNotFound();
    }
}
