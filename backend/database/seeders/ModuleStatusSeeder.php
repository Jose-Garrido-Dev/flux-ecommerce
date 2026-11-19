<?php

namespace Database\Seeders;

use App\Domain\System\Models\ModuleStatus;
use Illuminate\Database\Seeder;

/** Carga los 34 módulos del documento de requisitos. No sobrescribe estados ya editados. */
class ModuleStatusSeeder extends Seeder
{
    public const MODULES = [
        1 => ['Dashboard administrativo', 11], 2 => ['Gestión de productos', 3], 3 => ['Categorías y subcategorías', 3],
        4 => ['Marcas', 3], 5 => ['Atributos y variantes', 3], 6 => ['Inventario y bodegas', 4],
        7 => ['Pedidos', 8], 8 => ['Pasarelas de pago', 6], 9 => ['Envíos y logística', 7],
        10 => ['Clientes y cuentas', 5], 11 => ['Cupones y promociones', 10], 12 => ['Marketing y newsletter', 10],
        13 => ['Correos y notificaciones', 8], 14 => ['CMS y editor visual', 9], 15 => ['Biblioteca multimedia', 9],
        16 => ['Inteligencia artificial', 12], 17 => ['Finanzas y rentabilidad', 11], 18 => ['IVA, moneda y configuración comercial', 1],
        19 => ['SEO y blog', 10], 20 => ['WhatsApp y atención', 10], 21 => ['Reseñas y opiniones', 8],
        22 => ['Usuarios administrativos y permisos', 1], 23 => ['Auditoría', 1], 24 => ['Configuraciones generales', 1],
        25 => ['Manuales de uso', 1], 26 => ['Storefront público', 2], 27 => ['Experiencia visual y animaciones', 2],
        28 => ['Devoluciones, cambios y reembolsos', 8], 29 => ['Configuración técnica y operaciones', 1], 30 => ['Reutilización comercial Flux', 15],
        31 => ['Base de datos y reglas críticas', 1], 32 => ['Testing y calidad', 1], 33 => ['Criterios de finalización', 1],
        34 => ['Plan de implementación', null],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $number => [$name, $phase]) {
            ModuleStatus::query()->firstOrCreate(
                ['number' => $number],
                ['name' => $name, 'phase' => $phase, 'state' => 'pending'],
            );
        }
    }
}
