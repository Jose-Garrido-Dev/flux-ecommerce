<?php

namespace Database\Seeders;

use App\Domain\Pricing\Models\TaxClass;
use Illuminate\Database\Seeder;

/**
 * Clases de impuesto de Chile: IVA 19% (DL 825, art. 14) y exento.
 * Idempotente. La tasa se puede ajustar desde el panel si la ley cambia.
 */
class TaxClassSeeder extends Seeder
{
    public function run(): void
    {
        TaxClass::query()->firstOrCreate(['code' => 'iva'], [
            'name' => 'Afecto a IVA (19%)',
            'rate_bp' => 1900,
            'is_exempt' => false,
            'is_default' => true,
        ]);

        TaxClass::query()->firstOrCreate(['code' => 'exento'], [
            'name' => 'Exento de IVA',
            'rate_bp' => 0,
            'is_exempt' => true,
            'is_default' => false,
        ]);
    }
}
