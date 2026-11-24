<?php

namespace App\Filament\Resources\ShippingZones\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\ShippingZones\ShippingZoneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShippingZones extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = ShippingZoneResource::class;

    protected function helpManual(): string
    {
        return 'zonas-de-envio';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()];
    }
}
