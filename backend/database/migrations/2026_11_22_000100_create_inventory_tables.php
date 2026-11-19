<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4: inventario por bodega, movimientos inmutables, reservas con vencimiento y transferencias.
 * La disponibilidad de una variante = suma de (on_hand - reserved) en bodegas activas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 20)->unique();
            $table->string('address', 200)->nullable();
            $table->foreignId('region_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commune', 80)->nullable();
            $table->string('phone', 30)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('priority')->default(0)->comment('Orden en que se toma stock al reservar (menor primero)');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->unique()->constrained('product_variants')->cascadeOnDelete();
            $table->boolean('track_stock')->default(true);
            $table->boolean('allow_backorder')->default(false)->comment('Permite vender sin stock (sobreventa)');
            $table->unsignedInteger('min_stock')->default(0)->comment('Bajo este disponible se alerta stock bajo');
            $table->timestamps();
        });

        Schema::create('inventory_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('on_hand')->default(0)->comment('Stock físico');
            $table->integer('reserved')->default(0)->comment('Comprometido en carritos o pedidos sin despachar');
            $table->timestamps();

            $table->unique(['variant_id', 'warehouse_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->string('status', 16)->default('completed');
            $table->string('notes', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->unsignedInteger('quantity');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('type', 24);
            $table->integer('quantity')->comment('Cambio del stock físico, con signo');
            $table->integer('before_on_hand');
            $table->integer('after_on_hand');
            $table->integer('reserved_change')->default(0)->comment('Cambio del stock reservado (reservas y liberaciones)');
            $table->string('reason', 255)->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reverses_movement_id')->nullable()->unique()->constrained('stock_movements')->restrictOnDelete();
            $table->foreignId('stock_transfer_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variant_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('reference_type', 40)->comment('cart u order');
            $table->string('reference_id', 64);
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 16)->default('active')->comment('active, consumed, released, expired');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE inventory_levels ADD CONSTRAINT inventory_levels_reserved_non_negative CHECK (reserved >= 0)');
            DB::statement('ALTER TABLE stock_reservations ADD CONSTRAINT stock_reservations_quantity_positive CHECK (quantity > 0)');
            DB::statement("CREATE UNIQUE INDEX warehouses_one_default ON warehouses (is_default) WHERE is_default");
            // Los movimientos son un libro contable: no se editan ni se borran (las correcciones son contramovimientos)
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION stock_movements_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Los movimientos de inventario no se pueden modificar ni eliminar; registra un contramovimiento.';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER stock_movements_no_update BEFORE UPDATE OR DELETE ON stock_movements
                    FOR EACH ROW EXECUTE FUNCTION stock_movements_immutable();
            SQL);
        }

        // Bodega inicial (editable) y registro de inventario para las variantes existentes.
        // Las variantes creadas antes de esta fase quedan SIN control de stock para no dejar
        // la tienda agotada al actualizar; se activa por producto o por importación.
        $now = now();
        $warehouseId = DB::table('warehouses')->insertGetId([
            'name' => 'Bodega principal', 'code' => 'PRINCIPAL', 'status' => 'active',
            'priority' => 0, 'is_default' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('product_variants')->orderBy('id')->select('id')->chunk(500, function ($variants) use ($warehouseId, $now) {
            DB::table('inventory_items')->insert($variants->map(fn ($v) => [
                'variant_id' => $v->id, 'track_stock' => false, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
            DB::table('inventory_levels')->insert($variants->map(fn ($v) => [
                'variant_id' => $v->id, 'warehouse_id' => $warehouseId, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS stock_movements_no_update ON stock_movements');
            DB::statement('DROP FUNCTION IF EXISTS stock_movements_immutable()');
        }
        foreach (['stock_reservations', 'stock_movements', 'stock_transfer_items', 'stock_transfers', 'inventory_levels', 'inventory_items', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
