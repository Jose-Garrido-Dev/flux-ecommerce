<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Attributes\AttributeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttributes extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = AttributeResource::class;

    protected function helpManual(): string
    {
        return 'atributos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()];
    }
}
