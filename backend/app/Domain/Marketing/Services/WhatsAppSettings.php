<?php

namespace App\Domain\Marketing\Services;

use App\Domain\System\Services\SettingsRepository;

/**
 * Botón de WhatsApp (enlace wa.me con texto prearmado). No usa la API de WhatsApp Business ni servicios
 * externos: abre la conversación en el teléfono o en WhatsApp Web del cliente.
 */
class WhatsAppSettings
{
    public const POSITIONS = ['right' => 'Abajo a la derecha', 'left' => 'Abajo a la izquierda'];

    public const PAGES = ['all' => 'Todas las páginas', 'product' => 'Solo en las fichas de producto'];

    public function __construct(private readonly SettingsRepository $settings) {}

    /** Solo dígitos con código de país: +56 9 1234 5678 → 56912345678. */
    public static function digits(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '56'.$digits;
        }

        return $digits;
    }

    public static function isValidPhone(?string $phone): bool
    {
        return (bool) preg_match('/^56\d{9}$/', self::digits($phone)) || (bool) preg_match('/^\d{10,15}$/', self::digits($phone));
    }

    public function public(): ?array
    {
        $s = $this->settings->group('whatsapp');
        if (! ($s['enabled'] ?? false) || ! self::isValidPhone($s['phone'] ?? null)) {
            return null;
        }

        return [
            'phone' => self::digits($s['phone']),
            'message' => (string) ($s['message'] ?? ''),
            'product_message' => (string) ($s['product_message'] ?? ''),
            'position' => array_key_exists($s['position'] ?? '', self::POSITIONS) ? $s['position'] : 'right',
            'pages' => array_key_exists($s['pages'] ?? '', self::PAGES) ? $s['pages'] : 'all',
            'label' => (string) ($s['label'] ?? '') ?: 'Escríbenos por WhatsApp',
        ];
    }
}
