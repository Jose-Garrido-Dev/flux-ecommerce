<?php

namespace App\Http\Controllers\Api;

use App\Domain\System\Models\SystemHeartbeat;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    /** El proceso responde (liveness). */
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    /** Dependencias disponibles (readiness). 503 si falla la base de datos o la caché. */
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health:ping', 1, 10);

                return Cache::get('health:ping') === 1 ?: throw new \RuntimeException('cache');
            }),
        ];

        foreach (['scheduler', 'worker'] as $process) {
            $seen = SystemHeartbeat::query()->where('name', $process)->value('last_seen_at');
            $checks[$process] = $seen && now()->diffInMinutes($seen, true) <= 3 ? 'ok' : 'stale';
        }

        $critical = $checks['database'] === 'ok' && $checks['cache'] === 'ok';
        $processesOk = $checks['scheduler'] === 'ok' && $checks['worker'] === 'ok';
        $status = ! $critical ? 'fail' : ($processesOk ? 'ok' : 'degraded');

        return response()->json(
            ['status' => $status, 'checks' => $checks, 'version' => config('flux.version')],
            $critical ? 200 : 503,
        );
    }

    private function check(callable $probe): string
    {
        try {
            $probe();

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
