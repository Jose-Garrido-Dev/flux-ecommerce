<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Shipment;

/**
 * Integración con la API de un courier. Cada capacidad solo se usa si el adaptador la declara
 * y además quedó verificada con credenciales reales (pruebas del courier). Sin eso, el panel la
 * muestra como "No verificada" y el despacho se registra a mano (número de seguimiento y estados).
 */
interface CourierAdapter
{
    /** Capacidades implementadas en el código: quote (cotizar), label (etiqueta), tracking (estados por API), webhook. */
    public function capabilities(): array;

    /** Prueba real de conexión. @return string|null mensaje de error, o null si funcionó */
    public function verify(Courier $courier): ?string;

    /** Consulta los estados del envío en el courier. @return list<array{status: string, description: ?string, location: ?string, occurred_at: string, external_event_id: string}> */
    public function track(Courier $courier, Shipment $shipment): array;
}
