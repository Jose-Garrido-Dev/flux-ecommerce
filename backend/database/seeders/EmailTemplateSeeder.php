<?php

namespace Database\Seeders;

use App\Domain\Notifications\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/** Crea las plantillas de correo que falten con su texto por defecto. No toca las ya editadas. */
class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        EmailTemplate::syncDefaults();
    }
}
