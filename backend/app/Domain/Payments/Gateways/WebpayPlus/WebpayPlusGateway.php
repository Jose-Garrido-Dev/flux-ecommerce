<?php

namespace App\Domain\Payments\Gateways\WebpayPlus;

use App\Domain\Orders\Models\Payment;
use App\Domain\Payments\Contracts\PaymentGatewayDriver;
use App\Domain\Payments\Data\GatewayResult;
use App\Domain\Payments\Data\PaymentInitiation;
use App\Domain\Payments\Data\RefundResult;
use App\Domain\Payments\Data\ReturnReference;
use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\TransactionLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Webpay Plus de Transbank (API REST v1.2, la misma que usa su SDK oficial 5.x).
 *
 * Flujo: crear la transacción → el cliente paga en el formulario de Transbank → vuelve a nuestra
 * URL de retorno con token_ws → el servidor hace commit y solo si la respuesta trae
 * response_code 0, estado AUTHORIZED y el mismo monto y orden de compra, el pago queda aprobado.
 * Si el cliente anula, vuelve con TBK_TOKEN; si se le acaba el tiempo, solo con TBK_ORDEN_COMPRA.
 */
class WebpayPlusGateway implements PaymentGatewayDriver
{
    public const BASE_INTEGRATION = 'https://webpay3gint.transbank.cl/';

    public const BASE_PRODUCTION = 'https://webpay3g.transbank.cl/';

    private const ENDPOINT = 'rswebpaytransaction/api/webpay/v1.2/transactions';

    /** Credenciales públicas de integración que Transbank publica para pruebas (no cobran). */
    public const INTEGRATION_COMMERCE_CODE = '597055555532';

    public const INTEGRATION_API_KEY = '579B532A7440BB0C9079DED94D31EA1615BACEB56610332264630D42D0A36B1C';

    public const PAYMENT_TYPES = [
        'VD' => 'Débito', 'VN' => 'Crédito', 'VC' => 'Crédito en cuotas', 'SI' => 'Crédito, 3 cuotas sin interés',
        'S2' => 'Crédito, 2 cuotas sin interés', 'NC' => 'Crédito, cuotas sin interés', 'VP' => 'Prepago',
    ];

    public function __construct(private readonly PaymentGateway $config) {}

    public function code(): string
    {
        return 'webpay_plus';
    }

    public function checkoutName(): string
    {
        return 'Webpay';
    }

    public function checkoutDescription(): string
    {
        return 'Tarjetas de débito, crédito o prepago. Pagas en el sitio seguro de Transbank.';
    }

    public function capabilities(): array
    {
        return ['refund_total', 'refund_partial', 'status_query'];
    }

    public function credentialFields(): array
    {
        return ['commerce_code' => ['Código de comercio', false], 'api_key' => ['Llave secreta (API Key)', true]];
    }

    public function integrationCredentials(): ?array
    {
        return ['commerce_code' => self::INTEGRATION_COMMERCE_CODE, 'api_key' => self::INTEGRATION_API_KEY];
    }

    public function verify(): ?string
    {
        try {
            $response = $this->http()->post(self::ENDPOINT, $payload = [
                'buy_order' => 'VERIF'.now()->format('ymdHis'),
                'session_id' => 'verificacion-'.Str::lower(Str::random(8)),
                'amount' => 50,
                'return_url' => $this->returnUrl(),
            ]);
        } catch (ConnectionException) {
            TransactionLog::record(null, $this->code(), 'verify', 'error', null, null, $payload ?? null);

            return 'No se pudo conectar con Transbank. Revisa la conexión a internet del servidor.';
        }
        $ok = $response->successful() && filled($response->json('token')) && filled($response->json('url'));
        TransactionLog::record(null, $this->code(), 'verify', $ok ? 'ok' : 'error', null, $response->status(), $payload, $this->body($response));

        if ($ok) {
            return null;
        }

        return match ($response->status()) {
            401 => 'Transbank rechazó las credenciales: revisa el código de comercio y la llave secreta, y que correspondan al ambiente elegido.',
            default => 'Transbank respondió con un error '.$this->errorMessage($response).'. El detalle quedó en el registro de transacciones.',
        };
    }

    public function create(Payment $payment, string $returnUrl): PaymentInitiation
    {
        $payload = [
            'buy_order' => $payment->gateway_order,
            'session_id' => $payment->uuid,
            'amount' => $payment->amount,
            'return_url' => $returnUrl,
        ];
        try {
            $response = $this->http()->post(self::ENDPOINT, $payload);
        } catch (ConnectionException) {
            TransactionLog::record($payment->id, $this->code(), 'create', 'error', $payment->gateway_order, null, $payload);
            throw new GatewayException('No pudimos conectar con Webpay. Inténtalo de nuevo en unos segundos.');
        }
        $token = $response->json('token');
        $url = $response->json('url');
        $ok = $response->successful() && filled($token) && filled($url);
        TransactionLog::record($payment->id, $this->code(), 'create', $ok ? 'ok' : 'error', $token ?? $payment->gateway_order, $response->status(), $payload, $this->body($response));

        if (! $ok) {
            throw new GatewayException('Webpay no pudo iniciar el pago. Inténtalo de nuevo en unos minutos.');
        }

        return new PaymentInitiation((string) $url, (string) $token, 'token_ws');
    }

    public function parseReturn(array $params): ReturnReference
    {
        $tokenWs = $this->param($params, 'token_ws');
        $tbkToken = $this->param($params, 'TBK_TOKEN');
        $order = $this->param($params, 'TBK_ORDEN_COMPRA');

        // Anulado por el cliente (o error en el formulario): viene TBK_TOKEN
        if ($tbkToken !== null) {
            return new ReturnReference(token: $tbkToken, gatewayOrder: $order, aborted: true);
        }
        if ($tokenWs !== null) {
            return new ReturnReference(token: $tokenWs);
        }

        // Se acabó el tiempo en el formulario: solo vuelve la orden de compra
        return new ReturnReference(gatewayOrder: $order, timedOut: $order !== null);
    }

    public function confirm(Payment $payment): GatewayResult
    {
        $token = (string) $payment->external_id;
        try {
            $response = $this->http()->put(self::ENDPOINT.'/'.rawurlencode($token));
        } catch (ConnectionException) {
            TransactionLog::record($payment->id, $this->code(), 'commit', 'error', $token);

            return $this->status($payment); // no sabemos si se confirmó: se consulta
        }

        if (! $response->successful()) {
            TransactionLog::record($payment->id, $this->code(), 'commit', 'error', $token, $response->status(), null, $this->body($response));

            // Puede que ya se haya confirmado antes (reintento) o que no se haya autorizado: el estado lo dice
            return $this->status($payment);
        }

        $result = $this->toResult($response->json() ?? []);
        TransactionLog::record($payment->id, $this->code(), 'commit', $result->approved() ? 'ok' : 'rejected', $token, $response->status(), null, $this->body($response));

        return $result;
    }

    public function status(Payment $payment): GatewayResult
    {
        $token = (string) $payment->external_id;
        if ($token === '') {
            return new GatewayResult('pending', message: 'El pago aún no se inició en Webpay.');
        }
        try {
            $response = $this->http()->retry(3, 500, throw: false)->get(self::ENDPOINT.'/'.rawurlencode($token));
        } catch (ConnectionException) {
            TransactionLog::record($payment->id, $this->code(), 'status', 'error', $token);

            return new GatewayResult('error', message: 'No se pudo consultar a Webpay.');
        }
        if (! $response->successful()) {
            TransactionLog::record($payment->id, $this->code(), 'status', 'error', $token, $response->status(), null, $this->body($response));

            return new GatewayResult('error', message: 'Webpay no entregó el estado '.$this->errorMessage($response).'.');
        }
        $result = $this->toResult($response->json() ?? []);
        TransactionLog::record($payment->id, $this->code(), 'status', $result->status === 'rejected' ? 'rejected' : 'ok', $token, $response->status(), null, $this->body($response));

        return $result;
    }

    public function refund(Payment $payment, int $amount): RefundResult
    {
        $token = (string) $payment->external_id;
        $payload = ['amount' => $amount];
        try {
            $response = $this->http()->post(self::ENDPOINT.'/'.rawurlencode($token).'/refunds', $payload);
        } catch (ConnectionException) {
            TransactionLog::record($payment->id, $this->code(), 'refund', 'error', $token, null, $payload);

            return new RefundResult(false, message: 'No se pudo conectar con Webpay. El reembolso no se hizo; inténtalo de nuevo.');
        }
        $type = $response->json('type');
        $ok = $response->successful() && ($type === 'REVERSED' || ($type === 'NULLIFIED' && (int) $response->json('response_code') === 0));
        TransactionLog::record($payment->id, $this->code(), 'refund', $ok ? 'ok' : 'error', $token, $response->status(), $payload, $this->body($response));

        return $ok
            ? new RefundResult(true, $type, $response->json('authorization_code'))
            : new RefundResult(false, message: 'Webpay rechazó el reembolso '.$this->errorMessage($response).'. El detalle quedó en el registro de transacciones.');
    }

    /** Traduce la respuesta de commit o estado de Transbank. */
    private function toResult(array $data): GatewayResult
    {
        $status = (string) ($data['status'] ?? '');
        $code = isset($data['response_code']) ? (int) $data['response_code'] : null;
        $approvedStatuses = ['AUTHORIZED', 'CAPTURED', 'REVERSED', 'NULLIFIED', 'PARTIALLY_NULLIFIED'];

        $mapped = match (true) {
            $code === 0 && in_array($status, $approvedStatuses, true) => 'approved',
            $status === 'INITIALIZED' => 'pending',
            $status === 'FAILED', $code !== null && $code !== 0 => 'rejected',
            default => 'error',
        };

        return new GatewayResult(
            status: $mapped,
            amount: isset($data['amount']) ? (int) round((float) $data['amount']) : null,
            gatewayOrder: $data['buy_order'] ?? null,
            authorizationCode: $data['authorization_code'] ?? null,
            cardLast4: isset($data['card_detail']['card_number']) ? substr((string) $data['card_detail']['card_number'], -4) : null,
            paymentType: $data['payment_type_code'] ?? null,
            installments: isset($data['installments_number']) ? (int) $data['installments_number'] : null,
            message: $mapped === 'rejected' ? 'El banco rechazó el pago.' : null,
            rawStatus: $status ?: null,
        );
    }

    private function http(): PendingRequest
    {
        $commerce = $this->config->credential('commerce_code');
        $key = $this->config->credential('api_key');
        if (! $commerce || ! $key) {
            throw new GatewayException('Faltan el código de comercio o la llave secreta de Webpay.');
        }

        return Http::baseUrl($this->config->environment === 'production' ? self::BASE_PRODUCTION : self::BASE_INTEGRATION)
            ->withHeaders(['Tbk-Api-Key-Id' => $commerce, 'Tbk-Api-Key-Secret' => $key])
            ->acceptJson()->asJson()
            ->connectTimeout(10)->timeout(15);
    }

    private function returnUrl(): string
    {
        return route('payments.return', ['gateway' => $this->code()]);
    }

    private function param(array $params, string $key): ?string
    {
        $value = $params[$key] ?? null;

        return is_string($value) && $value !== '' && strlen($value) <= 200 ? $value : null;
    }

    private function body(Response $response): array
    {
        return is_array($json = $response->json()) ? $json : ['texto' => mb_substr($response->body(), 0, 300)];
    }

    /** Transbank responde sus errores en inglés: al panel solo se muestra el código; el texto original queda en el registro. */
    private function errorMessage(Response $response): string
    {
        return '(código '.$response->status().')';
    }
}
