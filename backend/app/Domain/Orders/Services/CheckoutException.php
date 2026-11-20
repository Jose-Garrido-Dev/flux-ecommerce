<?php

namespace App\Domain\Orders\Services;

use RuntimeException;

/** Error del checkout con código estable para la tienda (stock_insufficient, price_changed, …). */
class CheckoutException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $status = 422, public readonly ?array $summary = null)
    {
        parent::__construct($message);
    }
}
