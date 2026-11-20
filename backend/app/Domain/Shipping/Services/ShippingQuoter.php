<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Orders\Services\Tax;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\System\Services\SettingsRepository;
use Illuminate\Support\Collection;

/** Métodos de envío disponibles para una región y un subtotal (Fase 5: tarifa fija o retiro). */
class ShippingQuoter
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** @return Collection<int, array> */
    public function quote(?int $regionId, int $subtotal): Collection
    {
        return ShippingMethod::query()->active()->orderBy('position')->orderBy('id')->get()
            ->filter(fn (ShippingMethod $m) => $m->servesRegion($regionId))
            ->map(fn (ShippingMethod $m) => $this->present($m, $subtotal))
            ->values();
    }

    public function present(ShippingMethod $method, int $subtotal): array
    {
        $amount = $method->costFor($subtotal);
        $business = $this->settings->group('business');

        return [
            'code' => $method->code,
            'name' => $method->name,
            'type' => $method->type,
            'description' => $method->description,
            'amount' => $amount,
            'tax_amount' => Tax::included($amount, 1900),
            'free_over' => $method->free_over,
            'estimated_days' => $method->estimated_days,
            'pickup_address' => $method->type === 'pickup'
                ? trim(implode(', ', array_filter([$business['address'] ?? null, $business['commune'] ?? null])))
                : null,
            'method_id' => $method->id,
        ];
    }
}
