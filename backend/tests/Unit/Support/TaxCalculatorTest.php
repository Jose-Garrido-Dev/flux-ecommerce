<?php

namespace Tests\Unit\Support;

use App\Domain\Pricing\TaxCalculator;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    public static function inclusivePrices(): array
    {
        // bruto => [neto, iva]  (neto = round(bruto / 1,19), mitad hacia arriba)
        return [
            'precio típico' => [11990, 10076, 1914],
            'exacto' => [11900, 10000, 1900],
            'un peso' => [1, 1, 0],
            'cero' => [0, 0, 0],
            'tarifa despacho' => [3990, 3353, 637],
            'monto alto' => [1_999_990, 1_680_664, 319_326],
        ];
    }

    #[DataProvider('inclusivePrices')]
    public function test_splits_vat_inclusive_price(int $gross, int $net, int $tax): void
    {
        $split = (new TaxCalculator)->splitInclusive(Money::clp($gross));

        $this->assertSame($net, $split['net']->amount);
        $this->assertSame($tax, $split['tax']->amount);
        $this->assertSame($gross, $split['net']->amount + $split['tax']->amount);
    }

    public function test_adds_vat_to_net_price(): void
    {
        $result = (new TaxCalculator)->addToNet(Money::clp(10076));

        $this->assertSame(1914, $result['tax']->amount);
        $this->assertSame(11990, $result['gross']->amount);
    }

    public function test_exempt_rate_has_no_tax(): void
    {
        $split = (new TaxCalculator)->splitInclusive(Money::clp(5000), 0);

        $this->assertSame(5000, $split['net']->amount);
        $this->assertSame(0, $split['tax']->amount);
    }

    public function test_rejects_invalid_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TaxCalculator)->splitInclusive(Money::clp(1000), -1);
    }
}
