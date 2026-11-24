<?php

namespace App\Filament\Resources\Automations\Pages;

use App\Domain\Marketing\Services\AutomationRunner;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Automations\AutomationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAutomations extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = AutomationResource::class;

    public function mount(): void
    {
        AutomationRunner::seedDefaults();
        parent::mount();
    }

    public function getSubheading(): ?string
    {
        return 'Se revisan cada 10 minutos (requiere el programador de tareas). Solo llegan a suscritos confirmados del boletín.';
    }

    protected function helpManual(): string
    {
        return 'automatizaciones';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
