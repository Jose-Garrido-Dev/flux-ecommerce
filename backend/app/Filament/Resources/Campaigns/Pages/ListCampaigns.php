<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = CampaignResource::class;

    protected function helpManual(): string
    {
        return 'campanas';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()->label('Nueva campaña')];
    }
}
