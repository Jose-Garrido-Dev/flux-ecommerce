<?php

namespace Tests\Feature;

use App\Domain\System\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_are_audited_without_secrets(): void
    {
        $user = User::factory()->create(['name' => 'Ana']);
        $user->update(['name' => 'Ana María', 'password' => 'otraClaveSegura123']);

        $log = AuditLog::query()->where('action', 'User.updated')->latest('id')->firstOrFail();

        $this->assertSame('Ana', $log->old_values['name']);
        $this->assertSame('Ana María', $log->new_values['name']);
        $this->assertSame('[oculto]', $log->new_values['password']);
        $this->assertStringNotContainsString('otraClaveSegura123', json_encode($log->toArray()));
    }

    public function test_audit_logs_are_append_only(): void
    {
        User::factory()->create();
        $log = AuditLog::query()->firstOrFail();

        $this->expectException(LogicException::class);
        $log->update(['action' => 'manipulado']);
    }
}
