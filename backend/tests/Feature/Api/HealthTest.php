<?php

namespace Tests\Feature\Api;

use App\Domain\System\Models\SystemHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness(): void
    {
        $this->getJson('/api/health')->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_readiness_is_degraded_without_scheduler_heartbeat(): void
    {
        $this->getJson('/api/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.scheduler', 'stale');
    }

    public function test_readiness_ok_with_recent_heartbeats(): void
    {
        SystemHeartbeat::beat('scheduler');
        SystemHeartbeat::beat('worker');

        $this->getJson('/api/health/ready')->assertOk()->assertJsonPath('status', 'ok');
    }
}
