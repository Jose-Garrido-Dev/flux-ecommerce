<?php

namespace App\Filament\Resources\ShippingIncidents\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\ShippingIncidents\ShippingIncidentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageShippingIncidents extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = ShippingIncidentResource::class;

    protected function helpManual(): string
    {
        return 'incidencias';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
