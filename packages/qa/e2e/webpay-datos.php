<?php

/*
 * Ayudante de la prueba real con Webpay (ambiente de integración de Transbank).
 * Solo para CI o un entorno de prueba: nunca en producción.
 *
 *   php webpay-datos.php preparar            crea el retiro en tienda e imprime un SKU con stock
 *   php webpay-datos.php estado <número>     estado del pedido, pagos, transacciones y eventos (JSON)
 *   php webpay-datos.php reembolso <número> <monto>
 */

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Services\RefundService;
use App\Domain\Shipping\Models\ShippingMethod;

$backend = getenv('FLUX_BACKEND_DIR') ?: __DIR__.'/../../../backend';
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (app()->isProduction()) {
    fwrite(STDERR, "No se usa en producción.\n");
    exit(1);
}

[$_, $mode, $number, $amount] = $argv + [null, null, null, null];

try {
switch ($mode) {
    case 'preparar':
        ShippingMethod::query()->updateOrCreate(['code' => 'retiro'], ['name' => 'Retiro en tienda', 'type' => 'pickup', 'amount' => 0, 'status' => 'active']);
        $variantId = InventoryLevel::query()->selectRaw('variant_id, sum(on_hand - reserved) as libre')
            ->groupBy('variant_id')->havingRaw('sum(on_hand - reserved) >= 5')->orderBy('variant_id')->value('variant_id');
        echo ProductVariant::query()->findOrFail($variantId)->sku;
        break;

    case 'estado':
        $order = Order::query()->where('number', $number)->firstOrFail();
        echo json_encode([
            'pedido' => $order->only(['number', 'status', 'payment_status', 'grand_total', 'refunded_total']),
            'pagos' => $order->payments()->orderBy('attempt')->get()->map(fn ($p) => $p->only([
                'attempt', 'method_code', 'status', 'amount', 'gateway_order', 'external_id', 'authorization_code', 'card_last4',
                'payment_type', 'installments', 'failure_reason', 'refunded_amount',
            ])),
            'transacciones' => $order->payments()->with('transactions')->get()->flatMap->transactions
                ->map(fn ($t) => $t->only(['gateway', 'type', 'result', 'http_status', 'response'])),
            'eventos' => $order->events()->orderBy('id')->pluck('type'),
            'reembolsos' => $order->refunds()->get()->map->only(['amount', 'status', 'external_type', 'authorization_code']),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;

    case 'reembolso':
        $order = Order::query()->where('number', $number)->firstOrFail();
        $refund = app(RefundService::class)->refund($order, (int) $amount, 'Prueba de reembolso en integración');
        echo json_encode($refund->only(['amount', 'status', 'external_type', 'authorization_code']), JSON_UNESCAPED_UNICODE);
        break;

    default:
        fwrite(STDERR, "Uso: php webpay-datos.php preparar | estado <número> | reembolso <número> <monto>\n");
        exit(1);
}
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).': '.$e->getMessage()."\n");
    exit(1);
}
echo "\n";
