<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 10: cupones, promociones automáticas, descuentos del pedido, newsletter con doble opt-in,
 * campañas, automatizaciones, blog y redirecciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique()->comment('Siempre en mayúsculas');
            $table->string('name', 120);
            $table->string('type', 20)->comment('percent, fixed, free_shipping');
            $table->unsignedBigInteger('value')->default(0)->comment('Porcentaje (1-100) o monto en CLP');
            $table->unsignedBigInteger('min_subtotal')->nullable();
            $table->unsignedBigInteger('max_discount')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('stackable')->default(true)->comment('Se combina con promociones automáticas');
            $table->boolean('first_purchase_only')->default(false);
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::create('coupon_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->string('target_type', 20)->comment('product, category, brand');
            $table->unsignedBigInteger('target_id');
            $table->string('mode', 10)->default('include')->comment('include, exclude');
            $table->unique(['coupon_id', 'target_type', 'target_id']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->comment('Lo ve el cliente en el carrito');
            $table->string('type', 20)->comment('cart_amount, collection, qty_discount, buy_x_get_y');
            $table->json('conditions')->nullable()->comment('min_subtotal, min_quantity, buy_quantity, product_ids, category_ids, brand_ids');
            $table->json('actions')->comment('discount_type (percent, fixed), value, get_quantity');
            $table->integer('priority')->default(0);
            $table->boolean('stackable')->default(true)->comment('Se suma a otras promociones y al cupón');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->string('coupon_code', 40)->nullable()->after('currency');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 190)->index();
            $table->unsignedBigInteger('discount_amount');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->comment('coupon, promotion, manual');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('label', 160);
            $table->string('target', 10)->default('items')->comment('items o shipping');
            $table->unsignedBigInteger('amount');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('name', 120)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->comment('footer, checkout, account, admin, import');
            $table->string('status', 16)->default('pending')->comment('pending, subscribed, unsubscribed');
            $table->string('confirm_token_hash', 64)->nullable()->unique();
            $table->timestamp('confirm_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->comment('Solo para el panel');
            $table->string('subject', 150);
            $table->string('preheader', 150)->nullable();
            $table->string('heading', 150);
            $table->text('body')->comment('Markdown');
            $table->string('button_label', 60)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->string('audience', 20)->default('all')->comment('all, buyers, non_buyers');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 16)->default('draft')->comment('draft, scheduled, sending, sent, cancelled');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscriber_id')->nullable()->constrained('newsletter_subscribers')->nullOnDelete();
            $table->string('email', 190);
            $table->string('status', 16)->default('pending')->comment('pending, sent, failed, skipped');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->unique(['campaign_id', 'email']);
        });

        Schema::create('automations', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 30)->unique()->comment('welcome, abandoned_cart, post_purchase');
            $table->string('name', 120);
            $table->unsignedInteger('delay_hours')->default(0);
            $table->string('subject', 150);
            $table->string('heading', 150);
            $table->text('body');
            $table->string('button_label', 60)->nullable();
            $table->string('button_url', 500)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('active_since')->nullable()->comment('Solo se consideran hechos posteriores a la activación');
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('automation_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20)->comment('subscriber, cart, order');
            $table->unsignedBigInteger('subject_id');
            $table->string('email', 190);
            $table->string('status', 16)->comment('sent, failed, skipped');
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['automation_id', 'subject_type', 'subject_id']);
        });

        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->text('body')->comment('Markdown');
            $table->foreignId('category_id')->nullable()->constrained('blog_categories')->nullOnDelete();
            $table->foreignId('featured_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_name', 120)->nullable()->comment('Nombre público del autor');
            $table->string('status', 16)->default('draft')->comment('draft, published');
            $table->timestamp('published_at')->nullable()->index()->comment('Fecha futura = programado');
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 300)->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('code')->default(301);
            $table->boolean('is_automatic')->default(false)->comment('Creada al cambiar una dirección');
            $table->unsignedBigInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE coupons ADD CONSTRAINT coupons_percent_range CHECK (type <> 'percent' OR (value BETWEEN 1 AND 100))");
            DB::statement('ALTER TABLE redirects ADD CONSTRAINT redirects_code_valid CHECK (code IN (301, 302))');
            DB::statement('ALTER TABLE redirects ADD CONSTRAINT redirects_no_self CHECK (from_path <> to_path)');
        }
    }

    public function down(): void
    {
        foreach (['redirects', 'blog_posts', 'blog_categories', 'automation_runs', 'automations', 'campaign_recipients', 'campaigns',
            'newsletter_subscribers', 'order_adjustments', 'coupon_redemptions', 'promotions', 'coupon_targets', 'coupons'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('carts', fn (Blueprint $table) => $table->dropColumn('coupon_code'));
    }
};
