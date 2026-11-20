<?php

namespace App\Domain\Orders\Services;

use App\Domain\Orders\Models\Order;

/**
 * Token de seguimiento del pedido: HMAC del uuid con la clave de la aplicación.
 * No se puede adivinar, se puede volver a calcular (reintentos idempotentes) y en la base solo se guarda su hash.
 */
final class OrderTokens
{
    public static function for(string $uuid): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'order-tracking|'.$uuid, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function find(?string $token): ?Order
    {
        if (! $token || strlen($token) > 100) {
            return null;
        }

        return Order::query()->where('tracking_token_hash', self::hash($token))->first();
    }
}
