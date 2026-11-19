<?php

use App\Domain\System\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Schedule;

// Latido del scheduler: el panel y /api/health/ready alertan si deja de correr
Schedule::call(fn () => SystemHeartbeat::beat('scheduler'))
    ->name('flux:heartbeat')
    ->everyMinute();
