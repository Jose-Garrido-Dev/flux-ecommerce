<?php

namespace App\Filament\Resources\EmailLogs\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailLogs extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = EmailLogResource::class;

    protected function helpManual(): string
    {
        return 'correos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
