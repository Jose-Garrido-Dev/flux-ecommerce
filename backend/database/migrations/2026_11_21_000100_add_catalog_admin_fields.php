<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 (administración del catálogo): campos de SEO, envío, banners,
 * productos relacionados e importaciones masivas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('original_name')->nullable()->after('path');
            $table->json('variants')->nullable()->after('checksum')->comment('Miniaturas generadas: {"thumb": "ruta"}');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->foreignId('banner_media_id')->nullable()->after('logo_media_id')->constrained('media')->nullOnDelete();
            $table->string('country', 80)->nullable()->after('website');
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('banner_media_id')->nullable()->after('image_media_id')->constrained('media')->nullOnDelete();
            $table->string('icon', 64)->nullable();
            $table->string('color', 7)->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->boolean('is_required')->default(false)->after('visible_in_catalog');
        });

        Schema::table('attribute_values', function (Blueprint $table) {
            $table->foreignId('image_media_id')->nullable()->after('color_hex')->constrained('media')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('featured');
            $table->string('video_url')->nullable();
            // Envío (pestaña F)
            $table->boolean('is_fragile')->default(false);
            $table->boolean('special_packaging')->default(false);
            $table->boolean('requires_cold_chain')->default(false);
            $table->boolean('allows_pickup')->default(true);
            $table->string('shipping_notes', 500)->nullable()->comment('Restricciones de despacho visibles para el equipo');
            // SEO (pestaña G)
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 160)->nullable();
            $table->string('canonical_url')->nullable();
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type', 24)->comment('related, complementary, alternative, cross_sell, recommended');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'type']);
        });

        Schema::create('catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('path')->comment('Archivo en el disco local (privado)');
            $table->string('mode', 16)->default('upsert')->comment('upsert, create, update');
            $table->boolean('create_missing')->default(true)->comment('Crear marcas, categorías y valores que no existan');
            $table->json('mapping')->nullable()->comment('Columna del archivo => campo');
            $table->string('status', 16)->default('uploaded')->comment('uploaded, analyzed, queued, processing, done, failed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable()->comment('Resultado del análisis previo');
            $table->json('errors')->nullable()->comment('Errores por fila: [{row, sku, message}]');
            $table->text('failure')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_imports');
        Schema::dropIfExists('product_relations');

        Schema::table('products', fn (Blueprint $t) => $t->dropColumn([
            'tags', 'video_url', 'is_fragile', 'special_packaging', 'requires_cold_chain', 'allows_pickup',
            'shipping_notes', 'seo_title', 'seo_description', 'canonical_url',
        ]));
        Schema::table('attribute_values', fn (Blueprint $t) => $t->dropConstrainedForeignId('image_media_id'));
        Schema::table('attributes', fn (Blueprint $t) => $t->dropColumn('is_required'));
        Schema::table('categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('banner_media_id');
            $t->dropColumn(['icon', 'color', 'seo_title', 'seo_description']);
        });
        Schema::table('brands', function (Blueprint $t) {
            $t->dropConstrainedForeignId('banner_media_id');
            $t->dropColumn(['country', 'seo_title', 'seo_description']);
        });
        Schema::table('media', fn (Blueprint $t) => $t->dropColumn(['original_name', 'variants']));
    }
};
