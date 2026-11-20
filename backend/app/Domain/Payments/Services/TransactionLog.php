<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\PaymentTransaction;

/** Guarda cada llamada a una pasarela. Quita cualquier campo que pueda traer datos de tarjeta o credenciales. */
final class TransactionLog
{
    private const SENSITIVE = ['card_number', 'cvv', 'card_detail', 'Tbk-Api-Key-Secret', 'api_key', 'secret', 'access_token'];

    public static function record(?int $paymentId, string $gateway, string $type, string $result, ?string $externalId = null, ?int $httpStatus = null, ?array $request = null, ?array $response = null): PaymentTransaction
    {
        return PaymentTransaction::query()->create([
            'payment_id' => $paymentId,
            'gateway' => $gateway,
            'type' => $type,
            'external_id' => $externalId ? mb_substr($externalId, 0, 120) : null,
            'result' => $result,
            'http_status' => $httpStatus,
            'request' => $request === null ? null : self::clean($request),
            'response' => $response === null ? null : self::clean($response),
        ]);
    }

    private static function clean(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::SENSITIVE, true)) {
                // Solo se conservan los últimos 4 dígitos de la tarjeta
                $data[$key] = $key === 'card_detail' && is_array($value) && isset($value['card_number'])
                    ? ['card_number' => substr((string) $value['card_number'], -4)]
                    : '[omitido]';
            } elseif (is_array($value)) {
                $data[$key] = self::clean($value);
            }
        }

        return $data;
    }
}
