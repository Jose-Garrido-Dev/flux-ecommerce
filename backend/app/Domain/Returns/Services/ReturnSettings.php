<?php

namespace App\Domain\Returns\Services;

use App\Domain\System\Services\SettingsRepository;

/** Configuración → Devoluciones. Por defecto: 10 días (derecho a retracto de la Ley del Consumidor en compras a distancia). */
final class ReturnSettings
{
    public const DEFAULT_DAYS = 10;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->get('returns', 'enabled', true);
    }

    public function days(): int
    {
        return max(1, (int) $this->settings->get('returns', 'days', self::DEFAULT_DAYS));
    }

    public function allowExchange(): bool
    {
        return (bool) $this->settings->get('returns', 'allow_exchange', true);
    }

    /** Texto por defecto de cómo enviar el producto, que se puede ajustar en cada devolución. */
    public function instructions(): ?string
    {
        return $this->settings->get('returns', 'instructions') ?: null;
    }

    public function policy(): ?string
    {
        return $this->settings->get('returns', 'policy') ?: null;
    }
}
