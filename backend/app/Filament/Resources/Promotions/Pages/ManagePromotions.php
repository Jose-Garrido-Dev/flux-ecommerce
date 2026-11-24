<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePromotions extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = PromotionResource::class;

    protected function helpManual(): string
    {
        return 'promociones';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            CreateAction::make()->using(fn (array $data) => PromotionResource::persist(null, $data)),
        ];
    }
}
