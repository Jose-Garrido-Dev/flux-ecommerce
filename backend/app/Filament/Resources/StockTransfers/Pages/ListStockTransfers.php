<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockTransfers extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = StockTransferResource::class;

    protected function helpManual(): string
    {
        return 'transferencias';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()->label('Nueva transferencia')];
    }
}
