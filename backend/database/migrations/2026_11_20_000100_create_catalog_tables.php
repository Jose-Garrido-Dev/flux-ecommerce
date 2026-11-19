<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: multimedia, impuestos, marcas, categorías, atributos, productos,
 * variantes y precios. Ver diseño técnico, secciones 2.3 y 2.4.
 *
 * Regla central: todo producto tiene al menos una variante. Un producto simple
 * tiene exactamente una variante por defecto, así precio, stock y pedidos
 * siempre apuntan a product_variants.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('disk', 32)->default('public');
            $table->string('path')->comment('Ruta relativa dentro del disco');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size')->comment('Bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable()->comment('Texto alternativo (accesibilidad)');
            $table->string('title')->nullable();
            $table->char('checksum', 64)->nullable()->index()->comment('SHA-256 para detectar duplicados');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('rate_bp')->comment('Puntos base: 1900 = 19%');
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('normalized_name')->unique()->comment('Nombre en minúsculas y sin tildes, evita duplicados');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('website')->nullable();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('show_on_home')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->string('path')->default('')->index()->comment('Ids de ancestros materializados: /1/4/');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 16)->default('active')->index();
            $table->boolean('in_menu')->default(true);
            $table->boolean('on_home')->default(false);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('type', 16)->default('select')->comment('text, number, select, color');
            $table->string('unit', 16)->nullable();
            $table->string('display', 16)->default('pill')->comment('swatch, pill, select');
            $table->boolean('is_filterable')->default(false);
            $table->boolean('is_variant_axis')->default(false);
            $table->boolean('visible_in_catalog')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('value', 120)->comment('Valor normalizado usado en filtros y URLs');
            $table->string('label');
            $table->string('color_hex', 7)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description', 500)->nullable();
            $table->text('description')->nullable()->comment('HTML saneado');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('tax_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 16)->default('physical')->comment('physical, digital');
            $table->string('status', 16)->default('draft')->comment('draft, active, archived');
            $table->boolean('featured')->default(false);
            $table->boolean('has_variants')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['featured', 'status']);
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['product_id', 'category_id']);
            $table->index('category_id');
        });

        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('value_text')->nullable();
            $table->decimal('value_number', 14, 4)->nullable();

            $table->index(['attribute_id', 'attribute_value_id']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('barcode', 64)->nullable()->unique();
            $table->string('status', 16)->default('active')->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('weight_g')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();

            // Una variante tiene un solo valor por eje (no puede ser "Rojo" y "Azul" a la vez)
            $table->primary(['variant_id', 'attribute_id']);
            $table->index('attribute_value_id');
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_primary')->default(false);

            $table->unique(['product_id', 'media_id']);
        });

        Schema::create('variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->char('currency', 3)->default('CLP');
            $table->unsignedBigInteger('price_amount')->comment('Unidades mínimas; CLP sin decimales. IVA incluido');
            $table->unsignedBigInteger('compare_at_amount')->nullable()->comment('Precio "antes", debe ser mayor al precio');
            $table->unsignedBigInteger('cost_amount')->nullable()->comment('Costo interno, nunca se publica');
            $table->unsignedBigInteger('sale_amount')->nullable()->comment('Precio de oferta, menor al precio');
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->timestamps();

            $table->unique(['variant_id', 'currency']);
        });

        // Restricciones que la base garantiza aunque falle una validación en código
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX product_media_one_primary ON product_media (product_id) WHERE is_primary');
            DB::statement('CREATE UNIQUE INDEX product_variants_one_default ON product_variants (product_id) WHERE is_default');
            DB::statement('ALTER TABLE variant_prices ADD CONSTRAINT variant_prices_compare_gt_price CHECK (compare_at_amount IS NULL OR compare_at_amount > price_amount)');
            DB::statement('ALTER TABLE variant_prices ADD CONSTRAINT variant_prices_sale_lt_price CHECK (sale_amount IS NULL OR sale_amount < price_amount)');
            DB::statement('ALTER TABLE categories ADD CONSTRAINT categories_not_self_parent CHECK (parent_id IS NULL OR parent_id <> id)');
        }
    }

    public function down(): void
    {
        foreach ([
            'variant_prices', 'product_media', 'variant_attribute_values', 'product_variants',
            'product_attribute_values', 'category_product', 'products', 'attribute_values',
            'attributes', 'categories', 'brands', 'tax_classes', 'media',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
