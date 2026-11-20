<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Contracts\PaymentGatewayDriver;
use App\Domain\Payments\Gateways\WebpayPlus\WebpayPlusGateway;
use App\Domain\Payments\Models\PaymentGateway;

/** Pasarelas disponibles en el código. Mercado Pago, Flow y Khipu se agregan aquí con su propio driver. */
class GatewayRegistry
{
    public const DRIVERS = [
        'webpay_plus' => WebpayPlusGateway::class,
    ];

    public function has(string $code): bool
    {
        return isset(self::DRIVERS[$code]);
    }

    public function driver(string $code, ?PaymentGateway $config = null): PaymentGatewayDriver
    {
        $class = self::DRIVERS[$code] ?? throw new GatewayException("Pasarela desconocida: {$code}");
        $config ??= PaymentGateway::for($code) ?? throw new GatewayException('La pasarela no está configurada.');

        return new $class($config);
    }

    /** Pasarelas activas y verificadas, en orden. @return list<PaymentGateway> */
    public function available(): array
    {
        return PaymentGateway::query()->where('enabled', true)->orderBy('position')->get()
            ->filter(fn (PaymentGateway $g) => $this->has($g->code) && $g->isAvailable())
            ->values()->all();
    }

    public function isAvailable(string $code): bool
    {
        return collect($this->available())->contains(fn (PaymentGateway $g) => $g->code === $code);
    }
}
