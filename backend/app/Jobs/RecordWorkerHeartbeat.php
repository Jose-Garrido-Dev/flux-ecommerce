<?php

namespace App\Jobs;

use App\Domain\System\Models\SystemHeartbeat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * El scheduler encola este job cada minuto; si el worker lo procesa,
 * queda registrado su latido. Así /api/health/ready detecta colas detenidas.
 */
class RecordWorkerHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        SystemHeartbeat::beat('worker');
    }
}
