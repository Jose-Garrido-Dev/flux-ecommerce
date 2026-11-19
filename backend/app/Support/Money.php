<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

/**
 * Dinero en unidad mínima (CLP = pesos, sin decimales). Inmutable. Nunca usar float.
 */
final readonly class Money implements JsonSerializable
{
    private const DECIMALS = ['CLP' => 0, 'USD' => 2];

    public function __construct(public int $amount, public string $currency = 'CLP')
    {
        if (! isset(self::DECIMALS[$currency])) {
            throw new InvalidArgumentException("Moneda no soportada: {$currency}");
        }
    }

    public static function clp(int $amount): self
    {
        return new self($amount, 'CLP');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->amount * $quantity, $this->currency);
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    /** Formato chileno: $12.990 */
    public function format(): string
    {
        $decimals = self::DECIMALS[$this->currency];
        $value = $this->amount / (10 ** $decimals);
        $sign = $this->amount < 0 ? '-' : '';

        return $sign.'$'.number_format(abs($value), $decimals, ',', '.');
    }

    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency, 'formatted' => $this->format()];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('No se pueden operar monedas distintas.');
        }
    }
}
