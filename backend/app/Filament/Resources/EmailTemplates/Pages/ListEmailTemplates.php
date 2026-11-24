<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Domain\Notifications\Models\EmailTemplate;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailTemplates extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = EmailTemplateResource::class;

    public function mount(): void
    {
        EmailTemplate::syncDefaults();
        parent::mount();
    }

    protected function helpManual(): string
    {
        return 'correos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
