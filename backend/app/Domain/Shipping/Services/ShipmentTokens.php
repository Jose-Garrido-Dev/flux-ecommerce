<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Shipping\Models\Shipment;

/** Enlace público de seguimiento de un despacho: no se puede adivinar y no muestra datos personales. */
final class ShipmentTokens
{
    public static function for(string $uuid): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'shipment-tracking|'.$uuid, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function find(?string $token): ?Shipment
    {
        if (! $token || strlen($token) > 100) {
            return null;
        }

        return Shipment::query()->where('tracking_token_hash', self::hash($token))->first();
    }
}
