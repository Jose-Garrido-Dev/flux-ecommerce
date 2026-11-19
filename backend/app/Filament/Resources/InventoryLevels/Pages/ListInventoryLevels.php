<?php

namespace App\Filament\Resources\InventoryLevels\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\InventoryLevels\InventoryLevelResource;
use App\Filament\Support\InventoryActions;
use App\Filament\Widgets\InventoryAlerts;
use Filament\Resources\Pages\ListRecords;

class ListInventoryLevels extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = InventoryLevelResource::class;

    protected function helpManual(): string
    {
        return 'existencias';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), InventoryActions::movement()];
    }

    protected function getHeaderWidgets(): array
    {
        return [InventoryAlerts::class];
    }
}
