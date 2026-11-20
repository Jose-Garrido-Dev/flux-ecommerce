<?php

namespace App\Domain\Orders\Services;

use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Services\GatewayRegistry;
use App\Domain\System\Services\SettingsRepository;

/** Datos de la transferencia bancaria (Configuración → Pagos). */
class PaymentSettings
{
    public const DEFAULT_HOURS = 48;

    public const BANK_FIELDS = ['bank_name', 'account_type', 'account_number', 'account_holder', 'account_rut', 'account_email'];

    public function __construct(private readonly SettingsRepository $settings) {}

    public function transferEnabled(): bool
    {
        $s = $this->settings->group('payments');

        return (bool) ($s['bank_transfer_enabled'] ?? false)
            && collect(['bank_name', 'account_type', 'account_number', 'account_holder', 'account_rut'])->every(fn ($k) => filled($s[$k] ?? null));
    }

    public function transferHours(): int
    {
        return max(1, (int) $this->settings->get('payments', 'bank_transfer_hours', self::DEFAULT_HOURS));
    }

    /** Lo que ve el cliente para transferir. */
    public function transferInstructions(): array
    {
        $s = $this->settings->group('payments');

        return [
            'bank_name' => $s['bank_name'] ?? null,
            'account_type' => $s['account_type'] ?? null,
            'account_number' => $s['account_number'] ?? null,
            'account_holder' => $s['account_holder'] ?? null,
            'account_rut' => $s['account_rut'] ?? null,
            'account_email' => $s['account_email'] ?? null,
            'instructions' => $s['bank_transfer_instructions'] ?? null,
            'hours' => $this->transferHours(),
        ];
    }

    /**
     * Métodos de pago que ofrece el checkout: pasarelas activas y verificadas, y la transferencia.
     * test_mode avisa que la pasarela está en su ambiente de pruebas (no cobra de verdad).
     */
    public function methods(): array
    {
        $registry = app(GatewayRegistry::class);
        $methods = array_map(function (PaymentGateway $g) use ($registry) {
            $driver = $registry->driver($g->code, $g);

            return [
                'code' => $g->code, 'type' => 'redirect', 'name' => $driver->checkoutName(),
                'description' => $driver->checkoutDescription(), 'test_mode' => $g->isTestMode(),
            ];
        }, $registry->available());

        if ($this->transferEnabled()) {
            $methods[] = [
                'code' => 'bank_transfer', 'type' => 'manual', 'name' => 'Transferencia bancaria',
                'description' => 'Te mostramos los datos al confirmar. Reservamos tus productos por '.$this->transferHours().' horas.',
                'test_mode' => false,
            ];
        }

        return $methods;
    }

    public function isAvailable(string $code): bool
    {
        return $code === 'bank_transfer' ? $this->transferEnabled() : app(GatewayRegistry::class)->isAvailable($code);
    }
}
