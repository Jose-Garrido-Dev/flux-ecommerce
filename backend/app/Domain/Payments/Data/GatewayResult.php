<?php

namespace App\Domain\Payments\Data;

/**
 * Resultado de confirmar o consultar un pago en la pasarela.
 * status: approved (autorizado), rejected (rechazado por el banco), aborted (el cliente anuló),
 * pending (aún sin resultado), error (no se pudo saber: reintentar más tarde).
 */
final class GatewayResult
{
    public function __construct(
        public readonly string $status,
        public readonly ?int $amount = null,
        public readonly ?string $gatewayOrder = null,
        public readonly ?string $authorizationCode = null,
        public readonly ?string $cardLast4 = null,
        public readonly ?string $paymentType = null,
        public readonly ?int $installments = null,
        public readonly ?string $message = null,
        public readonly ?string $rawStatus = null,
    ) {}

    public function approved(): bool
    {
        return $this->status === 'approved';
    }

    public function final(): bool
    {
        return in_array($this->status, ['approved', 'rejected', 'aborted'], true);
    }
}
