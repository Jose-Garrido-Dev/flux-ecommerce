<?php

namespace App\Domain\Shipping\Couriers;

use App\Domain\Shipping\Contracts\CourierAdapter;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Services\CourierUnavailable;

/**
 * Courier sin conexión por API: el equipo ingresa el número de seguimiento y los estados a mano,
 * y el cliente consulta el detalle en la página pública del courier.
 * Chilexpress, Starken y Blue Express quedan así hasta contar con credenciales de prueba para
 * implementar y verificar su API (cotización, etiquetas y seguimiento).
 */
class ManualCourierAdapter implements CourierAdapter
{
    public function capabilities(): array
    {
        return [];
    }

    public function verify(Courier $courier): ?string
    {
        return "La conexión por API con {$courier->name} aún no está disponible: se necesitan credenciales de prueba del courier para implementarla y verificarla.";
    }

    public function track(Courier $courier, Shipment $shipment): array
    {
        throw new CourierUnavailable("El seguimiento automático con {$courier->name} no está disponible. Registra los estados a mano.");
    }
}
