<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = UserResource::class;

    protected function helpManual(): string
    {
        return 'usuarios';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()];
    }
}
