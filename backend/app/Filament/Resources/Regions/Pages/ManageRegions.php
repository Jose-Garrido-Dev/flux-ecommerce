<?php

namespace App\Filament\Resources\Regions\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Regions\RegionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRegions extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = RegionResource::class;

    protected function helpManual(): string
    {
        return 'regiones';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
