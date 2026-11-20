<?php

namespace App\Domain\Payments\Data;

/** Cómo llevar al cliente a la pasarela: un formulario POST a $url con el campo $field = $token. */
final class PaymentInitiation
{
    public function __construct(
        public readonly string $url,
        public readonly string $token,
        public readonly string $field = 'token_ws',
        public readonly string $method = 'POST',
    ) {}

    public function toArray(): array
    {
        return ['url' => $this->url, 'method' => $this->method, 'fields' => [$this->field => $this->token]];
    }
}
