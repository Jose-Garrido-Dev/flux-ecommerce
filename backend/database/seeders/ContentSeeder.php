<?php

namespace Database\Seeders;

use App\Domain\Content\Models\Menu;
use App\Domain\Content\Services\ThemeService;
use Illuminate\Database\Seeder;

/** Temas incluidos (activa Lapislázuli si no hay uno elegido) y los dos menús vacíos. Idempotente. */
class ContentSeeder extends Seeder
{
    public function run(ThemeService $themes): void
    {
        $themes->syncPresets();
        foreach (array_keys(Menu::LOCATIONS) as $location) {
            Menu::for($location);
        }
    }
}
