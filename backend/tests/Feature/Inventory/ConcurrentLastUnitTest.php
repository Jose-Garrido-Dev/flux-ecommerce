<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Throwable;

/**
 * Criterio de aceptación de la fase 4: 20 compras simultáneas por la última unidad.
 * Lanza 20 procesos reales (fork) que reservan la misma variante con 1 unidad, cada uno con su
 * propia conexión a PostgreSQL. Exactamente uno debe lograrlo y el stock nunca queda sobrevendido.
 * Necesita PostgreSQL y la extensión pcntl (así corre en la CI); SQLite no admite escrituras concurrentes.
 */
class ConcurrentLastUnitTest extends TestCase
{
    use RefreshDatabase;

    private const BUYERS = 20;

    public function test_twenty_simultaneous_purchases_for_the_last_unit(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requiere PostgreSQL y pcntl (se ejecuta en la CI).');
        }

        // Datos confirmados (fuera de la transacción de la prueba) para que los otros procesos los vean
        config(['database.connections.setup' => config('database.connections.pgsql')]);
        $setup = DB::connection('setup');
        $now = now();
        $productId = $setup->table('products')->insertGetId([
            'uuid' => (string) str()->uuid(), 'name' => 'Última unidad', 'slug' => 'ultima-unidad-'.uniqid(),
            'status' => 'active', 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $variantId = $setup->table('product_variants')->insertGetId([
            'product_id' => $productId, 'sku' => 'ULTIMA-'.uniqid(), 'status' => 'active', 'is_default' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $warehouseId = $setup->table('warehouses')->where('is_default', true)->value('id');
        $setup->table('inventory_items')->insert(['variant_id' => $variantId, 'track_stock' => true, 'created_at' => $now, 'updated_at' => $now]);
        $setup->table('inventory_levels')->insert(['variant_id' => $variantId, 'warehouse_id' => $warehouseId, 'on_hand' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $dir = sys_get_temp_dir().'/flux-concurrency-'.uniqid();
        mkdir($dir);
        $startAt = microtime(true) + 1.5;
        $pids = [];

        try {
            for ($i = 0; $i < self::BUYERS; $i++) {
                $pid = pcntl_fork();
                if ($pid === 0) {
                    $this->buyer($i, $variantId, $startAt, $dir);
                }
                $pids[] = $pid;
            }
            foreach ($pids as $pid) {
                pcntl_waitpid($pid, $status);
            }

            $results = array_map(fn ($i) => @file_get_contents("$dir/$i") ?: 'sin respuesta', range(0, self::BUYERS - 1));
            $counts = array_count_values($results);

            $this->assertSame(1, $counts['ok'] ?? 0, 'Solo una compra obtiene la última unidad. Resultados: '.json_encode($counts));
            $this->assertSame(self::BUYERS - 1, $counts['sin stock'] ?? 0, 'Las demás reciben "sin stock". Resultados: '.json_encode($counts));

            $level = $setup->table('inventory_levels')->where('variant_id', $variantId)->first();
            $this->assertSame(1, (int) $level->on_hand);
            $this->assertSame(1, (int) $level->reserved, 'Nunca se reserva más de lo que hay');
            $this->assertSame(1, $setup->table('stock_reservations')->where('variant_id', $variantId)->where('status', 'active')->count());
        } finally {
            // Limpieza de lo confirmado. TRUNCATE no dispara el trigger que protege los movimientos.
            $setup->statement('TRUNCATE stock_movements, stock_reservations');
            $setup->table('inventory_levels')->where('variant_id', $variantId)->delete();
            $setup->table('inventory_items')->where('variant_id', $variantId)->delete();
            $setup->table('product_variants')->where('id', $variantId)->delete();
            $setup->table('products')->where('id', $productId)->delete();
            $setup->disconnect();
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
        }
    }

    /** Proceso comprador: conexión propia, espera la largada y reserva 1 unidad. */
    private function buyer(int $n, int $variantId, float $startAt, string $dir): never
    {
        try {
            // Conexión nueva; las heredadas del proceso padre no se tocan
            config(['database.connections.buyer' => config('database.connections.pgsql')]);
            DB::setDefaultConnection('buyer');
            DB::connection('buyer')->getPdo();

            usleep((int) max(0, ($startAt - microtime(true)) * 1_000_000));
            app(InventoryService::class)->reserve($variantId, 1, 'cart', "comprador-$n");
            $result = 'ok';
        } catch (ValidationException) {
            $result = 'sin stock';
        } catch (Throwable $e) {
            $result = 'error: '.$e->getMessage();
        }
        file_put_contents("$dir/$n", $result);

        // Termina sin ejecutar destructores: cerrar las conexiones heredadas cortaría las del proceso padre
        posix_kill(posix_getpid(), SIGKILL);
        exit(0);
    }
}
