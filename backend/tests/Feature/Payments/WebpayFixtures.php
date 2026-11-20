<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\Gateways\WebpayPlus\WebpayPlusGateway;
use App\Domain\Payments\Models\PaymentGateway;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/** Webpay configurada con las credenciales públicas de integración y respuestas simuladas de Transbank. */
trait WebpayFixtures
{
    protected function enableWebpay(bool $verified = true): PaymentGateway
    {
        $gateway = PaymentGateway::for('webpay_plus');
        $gateway->credentials = ['commerce_code' => WebpayPlusGateway::INTEGRATION_COMMERCE_CODE, 'api_key' => WebpayPlusGateway::INTEGRATION_API_KEY];
        $gateway->enabled = true;
        $gateway->environment = 'integration';
        $gateway->save();
        if ($verified) {
            $gateway->update(['verified_at' => now(), 'verified_environment' => 'integration', 'verified_fingerprint' => $gateway->fingerprint()]);
        }

        return $gateway->fresh();
    }

    /** @param array<string, mixed> $commit respuesta del commit (PUT); null = Transbank responde error 422 */
    protected function fakeWebpay(?array $commit = null, ?array $status = null, ?array $refund = null): void
    {
        // Respuestas nuevas en cada llamada (Http::fake da prioridad a las primeras registradas)
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            'webpay3gint.transbank.cl/*/transactions/*/refunds' => Http::response($refund ?? ['type' => 'NULLIFIED', 'authorization_code' => '123456', 'response_code' => 0, 'nullified_amount' => 1000, 'balance' => 0]),
            'webpay3gint.transbank.cl/*/transactions/*' => function ($request) use ($commit, $status) {
                if ($request->method() === 'PUT') {
                    return $commit === null ? Http::response(['error_message' => 'Invalid status'], 422) : Http::response($commit);
                }

                return Http::response($status ?? ['status' => 'INITIALIZED', 'amount' => 0]);
            },
            'webpay3gint.transbank.cl/*/transactions' => fn ($request) => Http::response([
                'token' => 'tok'.str_pad((string) crc32($request['buy_order']), 61, '0', STR_PAD_LEFT),
                'url' => 'https://webpay3gint.transbank.cl/webpayserver/initTransaction',
            ]),
        ]);
    }

    protected function approved(int $amount, string $buyOrder, string $last4 = '6623'): array
    {
        return [
            'vci' => 'TSY', 'amount' => $amount, 'status' => 'AUTHORIZED', 'buy_order' => $buyOrder, 'session_id' => 'x',
            'card_detail' => ['card_number' => $last4], 'accounting_date' => '1120', 'transaction_date' => now()->toIso8601String(),
            'authorization_code' => '1213', 'payment_type_code' => 'VD', 'response_code' => 0, 'installments_number' => 0,
        ];
    }

    protected function rejected(int $amount, string $buyOrder): array
    {
        return [...$this->approved($amount, $buyOrder), 'status' => 'FAILED', 'response_code' => -1, 'authorization_code' => '000000'];
    }
}
