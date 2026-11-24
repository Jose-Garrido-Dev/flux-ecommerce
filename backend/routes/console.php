<?php

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Services\AutomationRunner;
use App\Domain\Marketing\Services\CampaignService;
use App\Domain\System\Models\SystemHeartbeat;
use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Artisan;
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
Schedule::command('orders:expire-unpaid')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();

// Marketing (Fase 10): campañas programadas y automatizaciones de correo
Artisan::command('marketing:send-due-campaigns', function (CampaignService $campaigns) {
    $due = Campaign::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->get();
    foreach ($due as $campaign) {
        $campaigns->start($campaign);
    }
    $this->info("Campañas iniciadas: {$due->count()}");
})->purpose('Inicia el envío de las campañas programadas que ya llegaron a su hora');

Artisan::command('marketing:run-automations', function (AutomationRunner $runner) {
    foreach ($runner->run() as $trigger => $sent) {
        $this->info("$trigger: $sent correos");
    }
})->purpose('Envía los correos automáticos (bienvenida, carrito abandonado, después de la compra)');

Schedule::command('marketing:send-due-campaigns')->everyMinute()->withoutOverlapping();
Schedule::command('marketing:run-automations')->everyTenMinutes()->withoutOverlapping();
