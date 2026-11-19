<?php

namespace App\Filament\Resources\ModuleStatuses\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\ModuleStatuses\ModuleStatusResource;
use Filament\Resources\Pages\ManageRecords;

class ManageModuleStatuses extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = ModuleStatusResource::class;

    protected function helpManual(): string
    {
        return 'tablero-de-avance';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
