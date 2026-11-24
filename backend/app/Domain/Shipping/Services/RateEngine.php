<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Orders\Services\Tax;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingRate;
use App\Domain\Shipping\Models\ShippingZone;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Support\Collection;

/**
 * Calcula qué métodos de envío sirven para una dirección y un carrito, y cuánto cuestan.
 * El mismo cálculo se usa en el checkout, al crear el pedido y en el simulador del panel.
 */
class RateEngine
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Métodos disponibles, ya con precio. @return Collection<int, array> */
    public function quote(?int $regionId, ?string $commune, int $subtotal, ShippingPackage $package): Collection
    {
        return $this->evaluateAll($regionId, $commune, $subtotal, $package)
            ->filter(fn ($r) => $r['available'])
            ->map(fn ($r) => $r['option'])
            ->values();
    }

    /**
     * Todos los métodos activos, disponibles o no, con el motivo (para el simulador).
     *
     * @return Collection<int, array{method: ShippingMethod, available: bool, reason: ?string, option: ?array}>
     */
    public function evaluateAll(?int $regionId, ?string $commune, int $subtotal, ShippingPackage $package): Collection
    {
        $zone = ShippingZone::match($regionId, $commune);

        return ShippingMethod::query()->active()->with(['rates', 'courier'])->orderBy('position')->orderBy('id')->get()
            ->map(fn (ShippingMethod $m) => ['method' => $m, ...$this->evaluate($m, $regionId, $commune, $subtotal, $package, $zone)]);
    }

    /** @return array{available: bool, reason: ?string, option: ?array} */
    public function evaluate(ShippingMethod $method, ?int $regionId, ?string $commune, int $subtotal, ShippingPackage $package, ?ShippingZone $zone = null): array
    {
        $no = fn (string $reason) => ['available' => false, 'reason' => $reason, 'option' => null];
        if ($method->status !== 'active') {
            return $no('El método está inactivo.');
        }
        $weight = $package->chargeableWeight($method->volumetric_factor);

        if ($method->type === 'pickup') {
            if (! $package->allowsPickup) {
                return $no('Hay productos que no se pueden retirar en tienda.');
            }

            return $this->ok($method, $method->amount, $subtotal, $weight, null, $method->estimated_days);
        }

        if ($package->requiresColdChain && ! $method->supports_cold_chain) {
            return $no('Hay productos que requieren cadena de frío y este método no la ofrece.');
        }
        if ($method->max_weight_g !== null && $weight > $method->max_weight_g) {
            return $no(sprintf('El paquete pesa %s y este método acepta hasta %s.', self::kg($weight), self::kg($method->max_weight_g)));
        }

        if ($method->type === 'flat') {
            if (! $method->servesRegion($regionId)) {
                return $no($regionId === null ? 'Falta elegir la región.' : 'No despacha a esta región.');
            }

            return $this->ok($method, $method->amount, $subtotal, $weight, null, $method->estimated_days);
        }

        // Tarifas por zona y peso
        if ($regionId === null && blank($commune)) {
            return $no('Falta elegir la región y la comuna.');
        }
        $zone ??= ShippingZone::match($regionId, $commune);
        if (! $zone) {
            return $no('Ninguna zona de envío incluye esta comuna.');
        }
        $rate = $this->rateFor($method, $zone->id, $weight);
        if (! $rate) {
            return $no(sprintf('No hay tarifa para %s en la zona %s.', self::kg($weight), $zone->name));
        }

        return $this->ok($method, $rate->amount, $subtotal, $weight, $zone, $rate->estimated_days ?? $method->estimated_days);
    }

    /** Primero las tarifas de la zona; si ninguna cubre el peso, las que valen para cualquier zona. */
    public function rateFor(ShippingMethod $method, int $zoneId, int $weightG): ?ShippingRate
    {
        $rates = $method->relationLoaded('rates') ? $method->rates : $method->rates()->get();

        return $rates->filter(fn (ShippingRate $r) => $r->zone_id === $zoneId && $r->covers($zoneId, $weightG))->first()
            ?? $rates->filter(fn (ShippingRate $r) => $r->zone_id === null && $r->covers($zoneId, $weightG))->first();
    }

    private function ok(ShippingMethod $method, int $amount, int $subtotal, int $weight, ?ShippingZone $zone, ?string $days): array
    {
        $amount = $method->isFreeFor($subtotal) ? 0 : $amount;

        return ['available' => true, 'reason' => null, 'option' => [
            'code' => $method->code,
            'name' => $method->name,
            'type' => $method->type,
            'description' => $method->description,
            'amount' => $amount,
            'tax_amount' => Tax::included($amount, 1900),
            'free_over' => $method->free_over,
            'estimated_days' => $days,
            'carrier_code' => $method->carrier_code,
            'carrier_name' => $method->courier?->name,
            'pickup_address' => $method->type === 'pickup' ? $this->pickupAddress($method) : null,
            'pickup_hours' => $method->type === 'pickup' ? $method->pickup_hours : null,
            'pickup_instructions' => $method->type === 'pickup' ? $method->pickup_instructions : null,
            'zone_id' => $zone?->id,
            'zone_name' => $zone?->name,
            'chargeable_weight_g' => $weight,
            'method_id' => $method->id,
        ]];
    }

    private function pickupAddress(ShippingMethod $method): ?string
    {
        if (filled($method->pickup_address)) {
            return $method->pickup_address;
        }
        $business = $this->settings->group('business');

        return trim(implode(', ', array_filter([$business['address'] ?? null, $business['commune'] ?? null]))) ?: null;
    }

    public static function kg(int $grams): string
    {
        return rtrim(rtrim(number_format($grams / 1000, 2, ',', '.'), '0'), ',').' kg';
    }
}
