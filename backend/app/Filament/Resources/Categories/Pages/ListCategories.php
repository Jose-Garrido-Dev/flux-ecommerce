<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = CategoryResource::class;

    protected function helpManual(): string
    {
        return 'categorias';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()];
    }
}
