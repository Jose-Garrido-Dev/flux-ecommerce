<?php

namespace Database\Seeders;

use App\Domain\Marketing\Services\AutomationRunner;
use Illuminate\Database\Seeder;

/** Automatizaciones con su texto por defecto, desactivadas hasta que la tienda las active. */
class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        AutomationRunner::seedDefaults();
    }
}
