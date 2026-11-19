<?php

namespace Tests\Feature;

use App\Domain\System\Models\SystemHeartbeat;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Configuración por defecto sin servicios externos: colas y caché en la base de datos.
 */
class DatabaseDriversTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_processes_jobs(): void
    {
        config(['queue.default' => 'database']);

        RecordWorkerHeartbeat::dispatch();
        $this->assertSame(1, DB::table('jobs')->count());

        Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertTrue(SystemHeartbeat::query()->where('name', 'worker')->exists());
    }

    public function test_database_cache_store_works(): void
    {
        Cache::store('database')->put('flux:test', 'ok', 60);

        $this->assertSame('ok', Cache::store('database')->get('flux:test'));
    }
}
