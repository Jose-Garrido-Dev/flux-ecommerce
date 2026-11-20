<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 6: pasarelas de pago.
 * - payment_gateways: configuración por pasarela, con credenciales cifradas y verificación contra el ambiente real.
 * - payment_transactions: registro de cada llamada y respuesta de la pasarela (sin datos de tarjeta). Solo se agregan filas.
 * - refunds: reembolsos totales o parciales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->boolean('enabled')->default(false);
            $table->string('environment', 20)->default('integration')->comment('integration, production');
            $table->text('credentials')->nullable()->comment('Cifradas con APP_KEY');
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_environment', 20)->nullable();
            $table->string('verified_fingerprint', 64)->nullable()->comment('Huella de las credenciales verificadas');
            $table->string('last_error', 500)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempt')->default(1)->after('method_code');
            $table->string('gateway_order', 30)->nullable()->unique()->after('external_id')->comment('Orden de compra enviada a la pasarela');
            $table->string('authorization_code', 20)->nullable();
            $table->string('card_last4', 4)->nullable();
            $table->string('payment_type', 10)->nullable()->comment('VD débito, VN crédito, VP prepago, etc.');
            $table->unsignedSmallInteger('installments')->nullable();
            $table->string('failure_reason', 200)->nullable();
            $table->unsignedBigInteger('refunded_amount')->default(0);
            $table->index(['method_code', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('gateway', 40);
            $table->string('type', 20)->comment('create, return, commit, status, refund, verify');
            $table->string('external_id', 120)->nullable()->index();
            $table->string('result', 20)->comment('ok, rejected, error');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 300);
            $table->string('status', 20)->comment('completed, failed');
            $table->string('external_type', 20)->nullable()->comment('Webpay: REVERSED o NULLIFIED');
            $table->string('authorization_code', 20)->nullable();
            $table->boolean('restocked')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE refunds ADD CONSTRAINT refunds_amount_positive CHECK (amount > 0)');
            DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_refund_not_over CHECK (refunded_amount <= amount)');
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION payment_transactions_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'El registro de transacciones de pago no se puede modificar';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER payment_transactions_no_update BEFORE UPDATE OR DELETE ON payment_transactions
                    FOR EACH ROW EXECUTE FUNCTION payment_transactions_immutable();
            SQL);
        }

        // Webpay Plus viene creada y apagada, en ambiente de integración (pruebas de Transbank)
        DB::table('payment_gateways')->insert([
            'code' => 'webpay_plus', 'name' => 'Webpay Plus', 'enabled' => false, 'environment' => 'integration',
            'position' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS payment_transactions_no_update ON payment_transactions');
        }
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_transactions');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['method_code', 'status']);
            $table->dropUnique(['gateway_order']);
            $table->dropColumn(['attempt', 'gateway_order', 'authorization_code', 'card_last4', 'payment_type', 'installments', 'failure_reason', 'refunded_amount']);
        });
        Schema::dropIfExists('payment_gateways');
    }
};
