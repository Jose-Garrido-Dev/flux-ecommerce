<?php

namespace Tests\Feature\Checkout;

use App\Domain\Cart\Models\Cart;
use App\Domain\Orders\Services\CheckoutException;
use App\Domain\Orders\Services\PlaceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Throwable;

/**
 * Plan de pruebas (sección 9): 20 checkouts simultáneos por 1 unidad crean exactamente 1 pedido.
 * Igual que ConcurrentLastUnitTest, pero recorriendo todo el checkout (PlaceOrder) en 20 procesos reales.
 * Necesita PostgreSQL y pcntl (así corre en la CI).
 */
class ConcurrentCheckoutTest extends TestCase
{
    use CheckoutFixtures, RefreshDatabase;

    private const BUYERS = 20;

    public function test_twenty_simultaneous_checkouts_for_the_last_unit_create_one_order(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere PostgreSQL y pcntl (se ejecuta en la CI).');
        }

        config(['database.connections.setup' => config('database.connections.pgsql')]);
        $setup = DB::connection('setup');
        $now = now();
        $suffix = uniqid();
        $productId = $setup->table('products')->insertGetId([
            'uuid' => (string) str()->uuid(), 'name' => 'Última unidad', 'slug' => "ultima-$suffix",
            'status' => 'active', 'published_at' => $now->copy()->subDay(), 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sku = "ULT-$suffix";
        $variantId = $setup->table('product_variants')->insertGetId([
            'product_id' => $productId, 'sku' => strtoupper($sku), 'status' => 'active', 'is_default' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $setup->table('variant_prices')->insert(['variant_id' => $variantId, 'currency' => 'CLP', 'price_amount' => 10000, 'created_at' => $now, 'updated_at' => $now]);
        $warehouseId = $setup->table('warehouses')->where('is_default', true)->value('id');
        $setup->table('inventory_items')->insert(['variant_id' => $variantId, 'track_stock' => true, 'created_at' => $now, 'updated_at' => $now]);
        $setup->table('inventory_levels')->insert(['variant_id' => $variantId, 'warehouse_id' => $warehouseId, 'on_hand' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $methodId = $setup->table('shipping_methods')->insertGetId(['code' => "dom-$suffix", 'name' => 'Despacho', 'type' => 'flat', 'amount' => 3990, 'status' => 'active', 'position' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $settings = ['bank_transfer_enabled' => true, 'bank_name' => 'Banco', 'account_type' => 'Corriente', 'account_number' => '1', 'account_holder' => 'Tienda', 'account_rut' => '76.123.456-0'];
        foreach ($settings as $key => $value) {
            $setup->table('settings')->insert(['group' => 'payments', 'key' => $key, 'value' => json_encode($value), 'created_at' => $now, 'updated_at' => $now]);
        }
        $regionCreated = false;
        if (! $setup->table('regions')->where('code', 'CL-RM')->exists()) {
            $setup->table('regions')->insert(['code' => 'CL-RM', 'roman' => 'RM', 'position' => 7, 'name' => 'Metropolitana de Santiago', 'is_enabled' => true, 'created_at' => $now, 'updated_at' => $now]);
            $regionCreated = true;
        }
        $cartIds = [];
        for ($i = 0; $i < self::BUYERS; $i++) {
            $cartIds[$i] = $setup->table('carts')->insertGetId(['uuid' => (string) str()->uuid(), 'token_hash' => hash('sha256', "c$i-$suffix"), 'status' => 'active', 'currency' => 'CLP', 'created_at' => $now, 'updated_at' => $now]);
            $setup->table('cart_items')->insert(['cart_id' => $cartIds[$i], 'variant_id' => $variantId, 'quantity' => 1, 'unit_price_snapshot' => 10000, 'created_at' => $now, 'updated_at' => $now]);
        }

        $dir = sys_get_temp_dir().'/flux-checkout-'.$suffix;
        mkdir($dir);
        $startAt = microtime(true) + 2;
        $pids = [];

        try {
            for ($i = 0; $i < self::BUYERS; $i++) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $this->buyer($i, $cartIds[$i], "dom-$suffix", $startAt, $dir);
                }
                $pids[] = $pid;
            }
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }

            $results = array_map(fn ($i) => @file_get_contents("$dir/$i") ?: 'sin respuesta', range(0, self::BUYERS - 1));
            $counts = array_count_values($results);

            $this->assertSame(1, $counts['ok'] ?? 0, 'Solo un checkout crea el pedido. Resultados: '.json_encode($counts));
            $this->assertSame(self::BUYERS - 1, ($counts['stock_insufficient'] ?? 0) + ($counts['cart_changed'] ?? 0), 'Los demás reciben "sin stock". Resultados: '.json_encode($counts));
            $this->assertSame(1, $setup->table('orders')->count());
            $level = $setup->table('inventory_levels')->where('variant_id', $variantId)->first();
            $this->assertSame(1, (int) $level->reserved, 'Nunca se reserva más de lo que hay');
            $this->assertSame(1, $setup->table('order_items')->count(), 'Los pedidos fallidos no dejan líneas');
        } finally {
            $setup->statement('TRUNCATE order_events, order_items, order_shipping_lines, payments, orders, consents, cart_items, carts, stock_movements, stock_reservations, audit_logs, jobs');
            $setup->table('settings')->where('group', 'payments')->delete();
            $setup->table('shipping_methods')->where('id', $methodId)->delete();
            $setup->table('inventory_levels')->where('variant_id', $variantId)->delete();
            $setup->table('inventory_items')->where('variant_id', $variantId)->delete();
            $setup->table('variant_prices')->where('variant_id', $variantId)->delete();
            $setup->table('product_variants')->where('id', $variantId)->delete();
            $setup->table('products')->where('id', $productId)->delete();
            if ($regionCreated) {
                $setup->table('regions')->where('code', 'CL-RM')->delete();
            }
            $setup->disconnect();
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
        }
    }

    private function buyer(int $n, int $cartId, string $method, float $startAt, string $dir): never
    {
        try {
            config(['database.connections.buyer' => config('database.connections.pgsql')]);
            DB::setDefaultConnection('buyer');
            DB::connection('buyer')->getPdo();
            Mail::fake();

            $cart = Cart::query()->findOrFail($cartId);
            usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));
            app(PlaceOrder::class)->handle($cart, $this->checkoutData(['shipping_method' => $method, 'email' => "c$n@correo.cl"]), null, "clave-$n");
            $result = 'ok';
        } catch (CheckoutException $e) {
            $result = $e->errorCode;
        } catch (Throwable $e) {
            $result = 'error: '.$e->getMessage();
        }
        file_put_contents("$dir/$n", $result);

        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }
}
