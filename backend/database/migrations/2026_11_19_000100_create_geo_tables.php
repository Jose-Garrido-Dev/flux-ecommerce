<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo geográfico de Chile: regiones y comunas.
 * Las regiones se cargan con RegionSeeder. Las comunas se cargarán desde
 * una fuente oficial verificada (código CUT) en la Fase 7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique()->comment('ISO 3166-2, p. ej. CL-RM');
            $table->string('roman', 5)->comment('Numeración romana tradicional');
            $table->unsignedTinyInteger('position')->comment('Orden geográfico norte a sur');
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->restrictOnDelete();
            $table->string('code', 8)->unique()->comment('Código Único Territorial (CUT)');
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communes');
        Schema::dropIfExists('regions');
    }
};
