<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8: devoluciones (solicitud, revisión, recepción y reembolso), plantillas de correo editables
 * e historial de envíos con reintentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('return')->comment('return = devolución con reembolso, exchange = cambio');
            $table->string('reason', 30);
            $table->text('customer_notes')->nullable();
            $table->string('status', 20)->default('requested');
            $table->text('rejection_reason')->nullable();
            $table->text('instructions')->nullable()->comment('Cómo y dónde enviar el producto, mostrado al cliente al aprobar');
            $table->text('resolution')->nullable();
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('condition', 20)->nullable()->comment('Al recibir: new, opened, damaged');
            $table->boolean('restock')->default(false);
            $table->timestamps();
            $table->unique(['return_id', 'order_item_id']);
        });

        Schema::create('return_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->string('path', 300)->comment('Disco local, carpeta privada');
            $table->string('original_name', 200)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('refunds', function (Blueprint $table) {
            $table->foreignId('return_id')->nullable()->after('order_id')->constrained('returns')->nullOnDelete();
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('subject', 200);
            $table->string('heading', 200);
            $table->text('intro')->comment('Texto principal en Markdown, con variables {así}');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('template_key', 60)->nullable()->index();
            $table->string('to', 255);
            $table->string('subject', 255)->nullable();
            $table->string('status', 16)->default('queued')->comment('queued, retrying, sent, failed, skipped');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('mailable')->nullable()->comment('Correo serializado para reenviarlo (los modelos se guardan como referencia)');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE return_items ADD CONSTRAINT return_items_quantity_positive CHECK (quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('return_id');
        });
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('return_evidence');
        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }
};
