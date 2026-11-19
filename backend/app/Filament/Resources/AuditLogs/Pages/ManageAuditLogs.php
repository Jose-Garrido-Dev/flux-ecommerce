<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAuditLogs extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = AuditLogResource::class;

    protected function helpManual(): string
    {
        return 'auditoria';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
