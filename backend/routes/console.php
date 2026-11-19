<?php

use App\Domain\System\Models\SystemHeartbeat;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Schedule;

// Latido del scheduler: el panel y /api/health/ready alertan si deja de correr
Schedule::call(fn () => SystemHeartbeat::beat('scheduler'))
    ->name('flux:heartbeat')
    ->everyMinute();

// Latido del worker de colas: se encola y el worker lo procesa
Schedule::job(new RecordWorkerHeartbeat)->everyMinute();

// Limpieza de trabajos fallidos antiguos (colas en base de datos)
Schedule::command('queue:prune-failed --hours=720')->daily();

// Libera el stock de reservas vencidas (carritos o pedidos sin pagar)
Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
