<?php

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBrands extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = BrandResource::class;

    protected function helpManual(): string
    {
        return 'marcas';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()];
    }
}
