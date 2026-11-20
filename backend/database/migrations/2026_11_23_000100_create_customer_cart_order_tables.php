<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5: clientes, carritos, métodos de envío, pedidos y pagos.
 * El pedido guarda una copia fija (snapshot) de productos, precios, IVA y dirección:
 * un cambio posterior en el catálogo no altera su historia. Pedidos y pagos nunca se borran.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tokens de acceso de clientes (Laravel Sanctum)
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 190)->unique()->comment('Siempre en minúsculas');
            $table->string('phone', 30)->nullable();
            $table->string('rut', 12)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->boolean('marketing_opt_in')->default(false);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customer_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40)->nullable()->comment('Casa, oficina…');
            $table->string('recipient', 120);
            $table->string('phone', 30);
            $table->string('street', 150);
            $table->string('number', 20);
            $table->string('apartment', 40)->nullable();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->foreignId('commune_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commune', 80)->comment('Nombre de la comuna (se guarda aunque exista commune_id)');
            $table->string('reference', 200)->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190)->nullable();
            $table->string('purpose', 40)->comment('terms, privacy, marketing');
            $table->boolean('granted');
            $table->string('source', 40);
            $table->string('ip', 45)->nullable();
            $table->string('text_version', 20)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_hash', 64)->unique()->comment('SHA-256 del token que guarda el navegador');
            $table->string('email', 190)->nullable();
            $table->string('currency', 3)->default('CLP');
            $table->string('status', 16)->default('active')->comment('active, converted, abandoned');
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price_snapshot')->comment('Solo referencia: en el checkout siempre se recalcula');
            $table->timestamps();
            $table->unique(['cart_id', 'variant_id']);
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->string('type', 20)->comment('flat (tarifa fija) o pickup (retiro)');
            $table->string('description', 255)->nullable();
            $table->unsignedBigInteger('amount')->default(0)->comment('CLP con IVA');
            $table->unsignedBigInteger('free_over')->nullable()->comment('Gratis desde este subtotal');
            $table->json('region_ids')->nullable()->comment('Vacío = todo Chile');
            $table->string('estimated_days', 40)->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('number', 20)->nullable()->unique()->comment('Correlativo visible');
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190)->index();
            $table->string('phone', 30)->nullable();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('channel', 20)->default('web');
            $table->string('currency', 3)->default('CLP');
            $table->string('status', 20)->default('pending')->comment('pending, processing, completed, cancelled');
            $table->string('payment_status', 20)->default('pending')->comment('pending, paid, expired, cancelled, refunded');
            $table->string('fulfillment_status', 20)->default('unfulfilled');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_total')->default(0);
            $table->unsignedBigInteger('tax_total');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('grand_total');
            $table->unsignedBigInteger('paid_total')->default(0);
            $table->unsignedBigInteger('refunded_total')->default(0);
            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();
            $table->string('document_type', 10)->default('boleta');
            $table->string('billing_rut', 12)->nullable();
            $table->string('billing_name', 150)->nullable();
            $table->string('billing_activity', 150)->nullable();
            $table->text('notes')->nullable();
            $table->string('tracking_token_hash', 64)->unique();
            $table->timestamp('placed_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name');
            $table->string('variant_label')->nullable();
            $table->string('sku', 64);
            $table->string('image_path')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price')->comment('Con IVA');
            $table->unsignedBigInteger('compare_at_price')->nullable();
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->unsignedInteger('tax_rate_bp');
            $table->unsignedBigInteger('tax_amount');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('order_shipping_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('method_id')->nullable()->constrained('shipping_methods')->nullOnDelete();
            $table->string('method_code', 40);
            $table->string('method_name', 120);
            $table->string('type', 20);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('tax_amount');
            $table->string('estimated_days', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('type', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('actor_type', 20)->comment('user, system, customer, provider');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('visible_to_customer')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->string('method_code', 40);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('CLP');
            $table->string('status', 20)->default('pending');
            $table->string('external_id', 100)->nullable();
            $table->string('reference', 120)->nullable()->comment('N.º de operación informado al confirmar');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['subtotal', 'tax_total', 'shipping_total', 'grand_total'] as $col) {
                DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_{$col}_nonneg CHECK ($col >= 0)");
            }
            DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_quantity_positive CHECK (quantity > 0)');
            // Una sola dirección por defecto por cliente
            DB::statement('CREATE UNIQUE INDEX addresses_one_default ON addresses (customer_id) WHERE is_default');
            // Historial del pedido: solo se agregan eventos
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION order_events_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'El historial del pedido no se puede modificar';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER order_events_no_update BEFORE UPDATE OR DELETE ON order_events
                    FOR EACH ROW EXECUTE FUNCTION order_events_immutable();
            SQL);
        }

        // Método de envío inicial: retiro en tienda (sin costo). La tarifa a domicilio se configura en el panel.
        DB::table('shipping_methods')->insert([
            'code' => 'retiro', 'name' => 'Retiro en tienda', 'type' => 'pickup', 'amount' => 0,
            'description' => 'Te avisamos por correo cuando esté listo para retirar.', 'status' => 'inactive', 'position' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS order_events_no_update ON order_events');
        }
        foreach (['payments', 'order_events', 'order_shipping_lines', 'order_items', 'orders', 'shipping_methods',
            'cart_items', 'carts', 'consents', 'addresses', 'customer_password_reset_tokens', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
