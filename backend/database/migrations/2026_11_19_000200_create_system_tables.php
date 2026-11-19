<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuración editable desde el panel (no secretos: esos van cifrados aparte)
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50);
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        // Auditoría: solo inserción (módulo 23)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 30)->comment('user, system, provider, customer');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 80);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('result', 20)->default('success');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_type', 'actor_id']);
            $table->index('created_at');
        });

        // Latidos de procesos (scheduler, workers) para alertas del dashboard
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamp('last_seen_at');
        });

        // Tablero de avance (módulo 33)
        Schema::create('module_statuses', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('name');
            $table->unsignedTinyInteger('phase')->nullable();
            $table->string('state', 20)->default('pending');
            $table->string('evidence_url')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_statuses');
        Schema::dropIfExists('system_heartbeats');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('settings');
    }
};
