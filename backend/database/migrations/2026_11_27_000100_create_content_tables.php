<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 9: constructor de páginas con versiones, menús, carpetas de la biblioteca multimedia y temas.
 * Las versiones de una página no se modifican: guardar crea una versión nueva y restaurar copia una anterior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('media', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('media_folders')->nullOnDelete();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 160);
            $table->string('slug', 160)->unique();
            $table->string('type', 20)->default('institutional'); // home, landing, institutional
            $table->string('status', 20)->default('draft'); // draft, published
            $table->string('seo_title', 160)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'status']);
        });

        Schema::create('page_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->json('blocks');
            $table->string('note', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('restored_from')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['page_id', 'number']);
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_version_id')->references('id')->on('page_versions')->nullOnDelete();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('location', 30)->unique(); // header, footer
            $table->string('name', 80);
            $table->timestamps();
        });
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label', 80);
            $table->string('link_type', 20); // url, page, category, brand, catalog
            $table->string('link_target', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['menu_id', 'parent_id', 'position']);
        });

        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('description', 300)->nullable();
            $table->json('tokens');
            $table->json('overrides')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX pages_one_home ON pages (type) WHERE type = 'home'");
            DB::statement('CREATE UNIQUE INDEX themes_one_active ON themes (is_active) WHERE is_active');
            DB::statement('ALTER TABLE page_versions ADD CONSTRAINT page_versions_number_positive CHECK (number > 0)');
            // Las versiones son inmutables: solo se insertan (restaurar crea una nueva)
            DB::unprepared(<<<'SQL'
                CREATE FUNCTION page_versions_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'Las versiones de una página no se modifican: guarda una versión nueva.';
                END;
                $$ LANGUAGE plpgsql;
                CREATE TRIGGER page_versions_no_update BEFORE UPDATE ON page_versions
                    FOR EACH ROW EXECUTE FUNCTION page_versions_immutable();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS page_versions_no_update ON page_versions; DROP FUNCTION IF EXISTS page_versions_immutable();');
        }
        Schema::table('pages', fn (Blueprint $t) => $t->dropForeign(['published_version_id']));
        Schema::dropIfExists('themes');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('page_versions');
        Schema::dropIfExists('pages');
        Schema::table('media', fn (Blueprint $t) => $t->dropConstrainedForeignId('folder_id'));
        Schema::dropIfExists('media_folders');
    }
};
