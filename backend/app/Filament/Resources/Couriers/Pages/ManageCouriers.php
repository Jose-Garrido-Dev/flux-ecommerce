<?php

namespace App\Filament\Resources\Couriers\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Couriers\CourierResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCouriers extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = CourierResource::class;

    protected function helpManual(): string
    {
        return 'couriers';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
