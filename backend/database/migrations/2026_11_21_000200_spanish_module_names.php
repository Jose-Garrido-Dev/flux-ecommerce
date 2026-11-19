<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Regla del proyecto: nada de inglés en pantalla. Corrige el nombre del módulo 1 en instalaciones existentes. */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('module_statuses')->where('number', 1)->where('name', 'Dashboard administrativo')
            ->update(['name' => 'Tablero de inicio']);
    }

    public function down(): void
    {
        DB::table('module_statuses')->where('number', 1)->where('name', 'Tablero de inicio')
            ->update(['name' => 'Dashboard administrativo']);
    }
};
