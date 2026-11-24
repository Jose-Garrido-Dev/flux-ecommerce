<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 7: zonas, tarifas por peso, couriers, despachos con seguimiento e incidencias.
 * Las comunas de una zona se guardan por nombre normalizado: funcionan aunque aún no esté cargado el listado oficial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('region_ids')->nullable()->comment('Regiones completas de la zona');
            $table->json('communes')->nullable()->comment('Comunas sueltas (nombres normalizados) que también pertenecen a la zona');
            $table->json('excluded_communes')->nullable()->comment('Comunas de esas regiones que NO pertenecen a la zona');
            $table->unsignedInteger('priority')->default(100)->comment('Menor número gana si una comuna cae en varias zonas');
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->string('carrier_code', 30)->nullable()->after('type');
            $table->unsignedInteger('volumetric_factor')->nullable()->after('free_over')->comment('cm³ por kg para el peso volumétrico (p. ej. 4000). Vacío = solo peso real');
            $table->unsignedInteger('max_weight_g')->nullable()->after('volumetric_factor');
            $table->boolean('supports_cold_chain')->default(false)->after('max_weight_g');
            $table->string('pickup_address', 255)->nullable()->after('supports_cold_chain');
            $table->string('pickup_hours', 255)->nullable()->after('pickup_address');
            $table->string('pickup_instructions', 500)->nullable()->after('pickup_hours');
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('method_id')->constrained('shipping_methods')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('shipping_zones')->restrictOnDelete()->comment('Vacío = cualquier zona');
            $table->unsignedInteger('min_weight_g')->default(0)->comment('Desde (incluido)');
            $table->unsignedInteger('max_weight_g')->nullable()->comment('Hasta (incluido). Vacío = sin límite');
            $table->unsignedBigInteger('amount')->comment('CLP con IVA');
            $table->string('estimated_days', 40)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['method_id', 'zone_id']);
        });

        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 80);
            $table->boolean('enabled')->default(false);
            $table->string('tracking_url_template', 255)->nullable()->comment('Página pública de seguimiento con {numero}');
            $table->text('credentials')->nullable()->comment('Cifradas');
            $table->json('verified_capabilities')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('order_shipping_lines', function (Blueprint $table) {
            $table->string('carrier_code', 30)->nullable()->after('type');
            $table->foreignId('zone_id')->nullable()->after('carrier_code')->constrained('shipping_zones')->nullOnDelete();
            $table->string('zone_name', 120)->nullable()->after('zone_id');
            $table->unsignedInteger('chargeable_weight_g')->nullable()->after('zone_name');
            $table->string('pickup_address', 255)->nullable()->after('chargeable_weight_g');
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('tracking_token_hash', 64)->unique()->comment('Enlace público de seguimiento (sin datos personales)');
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('carrier_code', 30)->nullable()->comment('null = despacho propio o retiro');
            $table->string('carrier_name', 80);
            $table->string('tracking_number', 80)->nullable();
            $table->string('tracking_url', 500)->nullable();
            $table->string('status', 24)->default('preparing');
            $table->unsignedSmallInteger('packages')->default(1);
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedBigInteger('cost_amount')->nullable()->comment('Lo que cobró el courier a la tienda');
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unique(['shipment_id', 'order_item_id']);
        });

        Schema::create('shipment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('description', 255)->nullable();
            $table->string('location', 120)->nullable();
            $table->string('source', 16)->default('admin')->comment('admin, courier, system');
            $table->string('external_event_id', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['shipment_id', 'external_event_id']);
        });

        Schema::create('shipping_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->string('status', 16)->default('open');
            $table->text('description');
            $table->text('resolution')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE shipping_rates ADD CONSTRAINT shipping_rates_weight_range CHECK (max_weight_g IS NULL OR max_weight_g >= min_weight_g)');
            DB::statement('ALTER TABLE shipment_items ADD CONSTRAINT shipment_items_quantity_positive CHECK (quantity > 0)');
        }

        $now = now();
        DB::table('couriers')->insert([
            ['code' => 'chilexpress', 'name' => 'Chilexpress', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'starken', 'name' => 'Starken', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'blue_express', 'name' => 'Blue Express', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_incidents');
        Schema::dropIfExists('shipment_events');
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
        Schema::table('order_shipping_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn(['carrier_code', 'zone_name', 'chargeable_weight_g', 'pickup_address']);
        });
        Schema::dropIfExists('couriers');
        Schema::dropIfExists('shipping_rates');
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropColumn(['carrier_code', 'volumetric_factor', 'max_weight_g', 'supports_cold_chain', 'pickup_address', 'pickup_hours', 'pickup_instructions']);
        });
        Schema::dropIfExists('shipping_zones');
    }
};
