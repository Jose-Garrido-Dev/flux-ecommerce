<?php

namespace Database\Seeders;

use App\Domain\Shipping\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Las 16 regiones de Chile (códigos ISO 3166-2:CL), ordenadas de norte a sur.
 * Idempotente: se puede ejecutar varias veces sin duplicar.
 */
class RegionSeeder extends Seeder
{
    public const REGIONS = [
        ['CL-AP', 'XV', 'Arica y Parinacota'],
        ['CL-TA', 'I', 'Tarapacá'],
        ['CL-AN', 'II', 'Antofagasta'],
        ['CL-AT', 'III', 'Atacama'],
        ['CL-CO', 'IV', 'Coquimbo'],
        ['CL-VS', 'V', 'Valparaíso'],
        ['CL-RM', 'RM', 'Metropolitana de Santiago'],
        ['CL-LI', 'VI', "Libertador General Bernardo O'Higgins"],
        ['CL-ML', 'VII', 'Maule'],
        ['CL-NB', 'XVI', 'Ñuble'],
        ['CL-BI', 'VIII', 'Biobío'],
        ['CL-AR', 'IX', 'La Araucanía'],
        ['CL-LR', 'XIV', 'Los Ríos'],
        ['CL-LL', 'X', 'Los Lagos'],
        ['CL-AI', 'XI', 'Aysén del General Carlos Ibáñez del Campo'],
        ['CL-MA', 'XII', 'Magallanes y de la Antártica Chilena'],
    ];

    public function run(): void
    {
        foreach (self::REGIONS as $i => [$code, $roman, $name]) {
            Region::query()->updateOrCreate(
                ['code' => $code],
                ['roman' => $roman, 'name' => $name, 'position' => $i + 1],
            );
        }
    }
}
