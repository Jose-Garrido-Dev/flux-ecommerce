<?php

namespace App\Domain\Payments\Data;

/** Lo que trae el regreso del cliente desde la pasarela: con qué pago se relaciona y si lo anuló. */
final class ReturnReference
{
    public function __construct(
        public readonly ?string $token = null,
        public readonly ?string $gatewayOrder = null,
        public readonly bool $aborted = false,
        public readonly bool $timedOut = false,
    ) {}
}
