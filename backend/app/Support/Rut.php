<?php

namespace App\Support;

/** Validación y formato de RUT chileno (módulo 11). */
final class Rut
{
    public static function clean(string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9kK]/', '', $rut) ?? '');
    }

    public static function isValid(string $rut): bool
    {
        $rut = self::clean($rut);
        if (strlen($rut) < 2) {
            return false;
        }

        $body = substr($rut, 0, -1);
        $dv = substr($rut, -1);

        return ctype_digit($body) && self::verifierDigit($body) === $dv;
    }

    public static function verifierDigit(string $body): string
    {
        $sum = 0;
        $factor = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += (int) $body[$i] * $factor;
            $factor = $factor === 7 ? 2 : $factor + 1;
        }
        $result = 11 - ($sum % 11);

        return match ($result) {
            11 => '0',
            10 => 'K',
            default => (string) $result,
        };
    }

    /** 12345678-5 → 12.345.678-5 */
    public static function format(string $rut): string
    {
        $rut = self::clean($rut);
        $body = substr($rut, 0, -1);

        return number_format((int) $body, 0, ',', '.').'-'.substr($rut, -1);
    }
}
