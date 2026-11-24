<?php

namespace Tests\Feature\Shipping;

use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\Shipping\Services\RateEngine;
use App\Domain\Shipping\Services\ShippingPackage;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Criterio de aceptación de la Fase 7: el motor de tarifas respeta cada límite. */
class RateEngineTest extends TestCase
{
    use RefreshDatabase;

    private RateEngine $engine;

    private int $rm;

    private int $valpo;

    private ShippingZone $santiago;

    private ShippingZone $regiones;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->engine = app(RateEngine::class);
        $this->rm = Region::query()->where('code', 'CL-RM')->value('id');
        $this->valpo = Region::query()->where('code', 'CL-VS')->value('id');
        // Santiago urbano (prioridad alta) con una comuna excluida; resto del país en otra zona
        $this->santiago = ShippingZone::query()->create(['name' => 'Santiago', 'region_ids' => [$this->rm], 'excluded_communes' => ['Lo Barnechea'], 'priority' => 10]);
        $this->regiones = ShippingZone::query()->create(['name' => 'Regiones', 'region_ids' => Region::query()->pluck('id')->all(), 'priority' => 100]);
    }

    private function courier(array $attrs = []): ShippingMethod
    {
        $method = ShippingMethod::query()->create(['code' => 'courier', 'name' => 'Courier', 'type' => 'table', 'amount' => 0, ...$attrs]);
        $method->rates()->createMany([
            ['zone_id' => $this->santiago->id, 'min_weight_g' => 0, 'max_weight_g' => 1000, 'amount' => 3000, 'position' => 1],
            ['zone_id' => $this->santiago->id, 'min_weight_g' => 1001, 'max_weight_g' => 5000, 'amount' => 5000, 'position' => 2],
            ['zone_id' => $this->regiones->id, 'min_weight_g' => 0, 'max_weight_g' => 5000, 'amount' => 7000, 'position' => 3],
        ]);

        return $method;
    }

    private function quote(ShippingMethod $method, int $grams, ?int $region = null, ?string $commune = 'Providencia', int $subtotal = 10000, ?ShippingPackage $package = null): array
    {
        return $this->engine->evaluate($method->fresh(), $region ?? $this->rm, $commune, $subtotal, $package ?? ShippingPackage::manual($grams));
    }

    public function test_weight_ranges_include_both_limits(): void
    {
        $m = $this->courier();
        $this->assertSame(3000, $this->quote($m, 0)['option']['amount']);
        $this->assertSame(3000, $this->quote($m, 1000)['option']['amount'], '1 kg exacto cae en el primer tramo');
        $this->assertSame(5000, $this->quote($m, 1001)['option']['amount']);
        $this->assertSame(5000, $this->quote($m, 5000)['option']['amount'], '5 kg exactos cae en el segundo tramo');
        $over = $this->quote($m, 5001);
        $this->assertFalse($over['available']);
        $this->assertStringStartsWith('No hay tarifa para', $over['reason']);
        $this->assertStringContainsString('zona Santiago', $over['reason']);
    }

    public function test_zone_priority_excluded_communes_and_unknown_destinations(): void
    {
        $m = $this->courier();
        $this->assertSame('Santiago', $this->quote($m, 500)['option']['zone_name']);
        // Comuna excluida de Santiago: cae en la siguiente zona que la incluye
        $barnechea = $this->quote($m, 500, commune: 'lo  barnechea');
        $this->assertSame('Regiones', $barnechea['option']['zone_name'], 'La comparación ignora mayúsculas y espacios');
        $this->assertSame(7000, $barnechea['option']['amount']);
        $this->assertSame('Regiones', $this->quote($m, 500, $this->valpo, 'Viña del Mar')['option']['zone_name']);

        // Zona por comuna puntual con prioridad mayor que la región
        ShippingZone::query()->create(['name' => 'Viña', 'communes' => ['Viña del Mar'], 'priority' => 5]);
        $this->assertSame('Viña', ShippingZone::match($this->valpo, 'Vina del Mar')?->name, 'Sin tildes también coincide');
        $none = $this->quote($m, 500, $this->valpo, 'Viña del Mar');
        $this->assertFalse($none['available'], 'La zona Viña no tiene tarifas en este método');

        $this->regiones->update(['status' => 'inactive']);
        $this->assertSame('Ninguna zona de envío incluye esta comuna.', $this->quote($m, 500, $this->valpo, 'Quilpué')['reason']);
        $this->assertSame('Falta elegir la región y la comuna.', $this->engine->evaluate($m->fresh(), null, null, 1000, ShippingPackage::manual(1))['reason']);
    }

    public function test_rates_without_zone_apply_to_any_zone_after_the_specific_ones(): void
    {
        $m = $this->courier();
        $m->rates()->create(['zone_id' => null, 'min_weight_g' => 0, 'max_weight_g' => null, 'amount' => 15000, 'position' => 9]);
        $this->assertSame(3000, $this->quote($m, 900)['option']['amount'], 'Primero la tarifa de la zona');
        $this->assertSame(15000, $this->quote($m, 30000)['option']['amount'], 'Sin tramo de la zona: la tarifa general sin límite de peso');
    }

    public function test_volumetric_weight_is_charged_when_larger_than_the_real_weight(): void
    {
        $m = $this->courier(['volumetric_factor' => 4000]);
        // Caja de 30 × 20 × 20 cm (12.000 cm³ / 4.000 = 3 kg) que pesa 500 g
        $box = ShippingPackage::manual(500, 300, 200, 200);
        $quote = $this->quote($m, 0, package: $box);
        $this->assertSame(5000, $quote['option']['amount'], 'Se cobra el tramo de 3 kg, no el de 500 g');
        $this->assertSame(3000, $quote['option']['chargeable_weight_g']);
        // Sin factor volumétrico se cobra el peso real
        $m->update(['volumetric_factor' => null]);
        $this->assertSame(500, $this->quote($m, 0, package: $box)['option']['chargeable_weight_g']);
        // Se multiplica por la cantidad
        $this->assertSame(1500, (new ShippingPackage([['weight_g' => 500, 'length_mm' => null, 'width_mm' => null, 'height_mm' => null, 'quantity' => 3]]))->chargeableWeight(4000));
    }

    public function test_free_shipping_threshold_is_inclusive(): void
    {
        $m = $this->courier(['free_over' => 50000]);
        $this->assertSame(3000, $this->quote($m, 500, subtotal: 49999)['option']['amount']);
        $this->assertSame(0, $this->quote($m, 500, subtotal: 50000)['option']['amount']);
        $flat = ShippingMethod::query()->create(['code' => 'fijo', 'name' => 'Fijo', 'type' => 'flat', 'amount' => 3990, 'free_over' => 30000]);
        $this->assertSame(0, $this->quote($flat, 500, subtotal: 30000)['option']['amount']);
    }

    public function test_maximum_weight_cold_chain_and_pickup_restrictions(): void
    {
        $flat = ShippingMethod::query()->create(['code' => 'fijo', 'name' => 'Fijo', 'type' => 'flat', 'amount' => 3990, 'max_weight_g' => 2000, 'region_ids' => [$this->rm]]);
        $this->assertTrue($this->quote($flat, 2000)['available'], 'El máximo está incluido');
        $this->assertSame('El paquete pesa 2,5 kg y este método acepta hasta 2 kg.', $this->quote($flat, 2500)['reason']);
        $this->assertSame('No despacha a esta región.', $this->quote($flat, 500, $this->valpo, 'Valparaíso')['reason']);

        $cold = new ShippingPackage([['weight_g' => 500, 'length_mm' => null, 'width_mm' => null, 'height_mm' => null, 'quantity' => 1]], true, true);
        $this->assertSame('Hay productos que requieren cadena de frío y este método no la ofrece.', $this->quote($flat, 0, package: $cold)['reason']);
        $flat->update(['supports_cold_chain' => true]);
        $this->assertTrue($this->quote($flat, 0, package: $cold)['available']);

        $pickup = ShippingMethod::query()->updateOrCreate(['code' => 'retiro'], ['name' => 'Retiro', 'type' => 'pickup', 'amount' => 0, 'status' => 'active', 'pickup_address' => 'Av. Italia 100, Providencia']);
        $noPickup = ShippingPackage::manual(500, allowsPickup: false);
        $this->assertSame('Hay productos que no se pueden retirar en tienda.', $this->quote($pickup, 0, package: $noPickup)['reason']);
        $ok = $this->quote($pickup, 500, null, null);
        $this->assertSame('Av. Italia 100, Providencia', $ok['option']['pickup_address']);

        $pickup->update(['status' => 'inactive']);
        $this->assertSame('El método está inactivo.', $this->quote($pickup, 500)['reason']);
    }

    public function test_quote_lists_only_available_methods_and_simulator_lists_all_with_reasons(): void
    {
        $this->courier();
        ShippingMethod::query()->create(['code' => 'fijo', 'name' => 'Fijo', 'type' => 'flat', 'amount' => 3990, 'region_ids' => [$this->valpo]]);
        $quote = $this->engine->quote($this->rm, 'Providencia', 10000, ShippingPackage::manual(500));
        $this->assertSame(['courier'], $quote->pluck('code')->all());
        $all = $this->engine->evaluateAll($this->rm, 'Providencia', 10000, ShippingPackage::manual(500));
        $this->assertCount(2, $all);
        $this->assertSame('No despacha a esta región.', $all->firstWhere('method.code', 'fijo')['reason']);
    }
}
