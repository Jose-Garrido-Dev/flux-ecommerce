<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Shipping\Contracts\CourierAdapter;
use App\Domain\Shipping\Couriers\ManualCourierAdapter;

/** Adaptador de cada courier. Al implementar la API de uno, se reemplaza aquí su clase. */
class CourierRegistry
{
    public const CAPABILITIES = [
        'quote' => 'Cotizar en el checkout',
        'label' => 'Generar etiquetas',
        'tracking' => 'Seguimiento automático',
        'webhook' => 'Avisos del courier',
    ];

    public const ADAPTERS = [
        'chilexpress' => ManualCourierAdapter::class,
        'starken' => ManualCourierAdapter::class,
        'blue_express' => ManualCourierAdapter::class,
    ];

    public function adapter(string $code): CourierAdapter
    {
        $class = self::ADAPTERS[$code] ?? ManualCourierAdapter::class;

        return app($class);
    }
}
