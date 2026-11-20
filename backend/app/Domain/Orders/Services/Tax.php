<?php

namespace App\Domain\Orders\Services;

/**
 * IVA incluido en el precio, calculado por línea (diseño técnico, sección 8.3):
 * neto = round(bruto / (1 + tasa)), iva = bruto - neto, redondeo al peso con mitad hacia arriba.
 */
final class Tax
{
    public static function included(int $gross, int $rateBp): int
    {
        if ($rateBp <= 0 || $gross <= 0) {
            return 0;
        }
        // Aritmética entera: evita errores de coma flotante
        $net = intdiv($gross * 10000 * 2 + (10000 + $rateBp), (10000 + $rateBp) * 2);

        return $gross - $net;
    }
}
