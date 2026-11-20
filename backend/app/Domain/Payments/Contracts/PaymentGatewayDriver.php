<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Orders\Models\Payment;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Data\PaymentInitiation;
use App\Domain\Payments\Data\RefundResult;
use App\Domain\Payments\Data\ReturnReference;

/**
 * Contrato de una pasarela de pago (diseño técnico, sección 7.1).
 * Reglas comunes: el retorno del navegador nunca marca un pago como pagado por sí solo; siempre se
 * consulta o confirma con la pasarela desde el servidor, y el monto se compara con el del pedido.
 * Cada llamada queda en payment_transactions sin datos de tarjeta.
 */
interface PaymentGatewayDriver
{
    public function code(): string;

    /** Nombre y descripción que ve el cliente en el checkout. */
    public function checkoutName(): string;

    public function checkoutDescription(): string;

    /** @return list<string> capacidades: refund_total, refund_partial, status_query */
    public function capabilities(): array;

    /** Credenciales que pide el panel: clave => [etiqueta, secreta]. */
    public function credentialFields(): array;

    /** Credenciales públicas de prueba del proveedor, si las publica. */
    public function integrationCredentials(): ?array;

    /** Prueba las credenciales contra el ambiente real sin cobrar nada. Devuelve un mensaje de error o null si funcionan. */
    public function verify(): ?string;

    public function create(Payment $payment, string $returnUrl): PaymentInitiation;

    /** Lee los parámetros con que vuelve el cliente. No confirma nada. */
    public function parseReturn(array $params): ReturnReference;

    /** Confirma el pago con la pasarela (Webpay: commit). Si no se puede, consulta el estado. */
    public function confirm(Payment $payment): GatewayResult;

    public function status(Payment $payment): GatewayResult;

    public function refund(Payment $payment, int $amount): RefundResult;
}
