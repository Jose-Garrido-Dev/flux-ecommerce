<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\System\Services\AuditLogger;

/**
 * Prueba real de conexión con una pasarela. Solo si responde bien queda "verificada"
 * para el ambiente y las credenciales actuales; si estas cambian, la verificación deja de valer.
 */
class GatewayVerifier
{
    public function __construct(private GatewayRegistry $registry) {}

    /** @return string|null mensaje de error, o null si quedó verificada */
    public function verify(PaymentGateway $gateway): ?string
    {
        $error = $this->registry->driver($gateway->code, $gateway)->verify();
        if ($error !== null) {
            $gateway->update(['last_error' => mb_substr($error, 0, 500), 'verified_at' => null]);

            return $error;
        }
        $gateway->update([
            'verified_at' => now(), 'verified_environment' => $gateway->environment,
            'verified_fingerprint' => $gateway->fingerprint(), 'last_error' => null,
        ]);
        app(AuditLogger::class)->record('PaymentGateway.verified', $gateway, [], ['environment' => $gateway->environment]);

        return null;
    }
}
