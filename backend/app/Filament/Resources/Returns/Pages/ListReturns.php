<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Returns\ReturnResource;
use Filament\Resources\Pages\ListRecords;

class ListReturns extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = ReturnResource::class;

    protected function helpManual(): string
    {
        return 'devoluciones';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
