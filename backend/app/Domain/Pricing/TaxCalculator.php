<?php

namespace App\Domain\Pricing;

use App\Support\Money;
use InvalidArgumentException;

/**
 * Cálculo de IVA. Las tasas se expresan en puntos base (1900 = 19%).
 * Regla del diseño (sección 8.3): por línea, neto = round(bruto / (1 + tasa)),
 * redondeo al peso con mitad hacia arriba, IVA = bruto - neto.
 */
final class TaxCalculator
{
    public const CHILE_VAT_BP = 1900;

    /** @return array{net: Money, tax: Money, gross: Money} */
    public function splitInclusive(Money $gross, int $rateBp = self::CHILE_VAT_BP): array
    {
        $this->assertRate($rateBp);

        // Aritmética entera: neto = round(bruto * 10000 / (10000 + tasa)), mitad hacia arriba
        $denominator = 10000 + $rateBp;
        $net = intdiv($gross->amount * 10000 * 2 + $denominator, $denominator * 2);

        return [
            'net' => new Money($net, $gross->currency),
            'tax' => new Money($gross->amount - $net, $gross->currency),
            'gross' => $gross,
        ];
    }

    /** @return array{net: Money, tax: Money, gross: Money} */
    public function addToNet(Money $net, int $rateBp = self::CHILE_VAT_BP): array
    {
        $this->assertRate($rateBp);
        $tax = intdiv($net->amount * $rateBp * 2 + 10000, 20000);

        return [
            'net' => $net,
            'tax' => new Money($tax, $net->currency),
            'gross' => new Money($net->amount + $tax, $net->currency),
        ];
    }

    private function assertRate(int $rateBp): void
    {
        if ($rateBp < 0 || $rateBp > 10000) {
            throw new InvalidArgumentException('Tasa de impuesto fuera de rango.');
        }
    }
}
