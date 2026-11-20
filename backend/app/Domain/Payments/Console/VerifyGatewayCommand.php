<?php

namespace App\Domain\Payments\Console;

use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Services\GatewayRegistry;
use App\Domain\Payments\Services\GatewayVerifier;
use Illuminate\Console\Command;

/** Lo mismo que el botón "Probar conexión" del panel, para usarlo desde el servidor. */
class VerifyGatewayCommand extends Command
{
    protected $signature = 'payments:verify {gateway=webpay_plus : Código de la pasarela}
        {--integration : Carga antes las credenciales públicas de prueba del ambiente de integración}
        {--enable : Si la prueba resulta bien, deja la pasarela activa en el checkout}';

    protected $description = 'Prueba la conexión real con una pasarela de pago y la marca como verificada';

    public function handle(GatewayRegistry $registry, GatewayVerifier $verifier): int
    {
        $code = (string) $this->argument('gateway');
        if (! $registry->has($code) || ! ($gateway = PaymentGateway::for($code))) {
            $this->error("La pasarela {$code} no existe.");

            return self::FAILURE;
        }
        if ($this->option('integration')) {
            $gateway->environment = 'integration';
            $gateway->credentials = $registry->driver($code, $gateway)->integrationCredentials();
            $gateway->save();
            $this->line('Se cargaron las credenciales de prueba del ambiente de integración.');
        }
        if (! $gateway->credentials) {
            $this->error('La pasarela no tiene credenciales. Configúralas en el panel (Configuración → Pagos).');

            return self::FAILURE;
        }

        $error = $verifier->verify($gateway);
        if ($error !== null) {
            $this->error("La prueba falló: {$error}");

            return self::FAILURE;
        }
        if ($this->option('enable')) {
            $gateway->update(['enabled' => true]);
        }
        $this->info("{$gateway->name}: {$gateway->fresh()->statusLabel()}.");

        return self::SUCCESS;
    }
}
