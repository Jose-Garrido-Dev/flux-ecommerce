<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Domain\Content\Models\Menu;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Menus\MenuResource;
use Filament\Resources\Pages\ListRecords;

class ListMenus extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = MenuResource::class;

    public function mount(): void
    {
        foreach (array_keys(Menu::LOCATIONS) as $location) {
            Menu::for($location);
        }
        parent::mount();
    }

    protected function helpManual(): string
    {
        return 'menus';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
