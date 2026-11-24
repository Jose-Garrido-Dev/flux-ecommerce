<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Shipments\ShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListShipments extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = ShipmentResource::class;

    protected function helpManual(): string
    {
        return 'despachos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
