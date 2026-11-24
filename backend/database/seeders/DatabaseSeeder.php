<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Datos base REALES necesarios en toda instalación (también en producción).
 * Nunca agregar aquí productos, clientes o pedidos de ejemplo: esos los crea el comando `flux:demo`,
 * que solo se ejecuta en desarrollo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            TaxClassSeeder::class,
            RolesAndPermissionsSeeder::class,
            ModuleStatusSeeder::class,
            EmailTemplateSeeder::class,
        ]);
    }
}
