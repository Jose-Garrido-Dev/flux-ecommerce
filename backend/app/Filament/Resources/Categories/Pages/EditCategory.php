<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = CategoryResource::class;

    protected function helpManual(): string
    {
        return 'categorias';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
