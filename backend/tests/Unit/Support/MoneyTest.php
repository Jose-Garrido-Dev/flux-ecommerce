<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_chilean_pesos(): void
    {
        $this->assertSame('$12.990', Money::clp(12990)->format());
        $this->assertSame('$1.250.000', Money::clp(1_250_000)->format());
        $this->assertSame('$0', Money::clp(0)->format());
        $this->assertSame('-$3.990', Money::clp(-3990)->format());
    }

    public function test_arithmetic_is_immutable(): void
    {
        $price = Money::clp(4990);
        $total = $price->multiply(3)->add(Money::clp(3990))->subtract(Money::clp(1000));

        $this->assertSame(17960, $total->amount);
        $this->assertSame(4990, $price->amount);
    }

    public function test_rejects_mixed_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::clp(100)->add(new Money(100, 'USD'));
    }

    public function test_rejects_unknown_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(100, 'XXX');
    }
}
