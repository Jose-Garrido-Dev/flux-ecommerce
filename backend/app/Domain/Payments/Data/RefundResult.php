<?php

namespace App\Domain\Payments\Data;

final class RefundResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $type = null,
        public readonly ?string $authorizationCode = null,
        public readonly ?string $message = null,
    ) {}
}
