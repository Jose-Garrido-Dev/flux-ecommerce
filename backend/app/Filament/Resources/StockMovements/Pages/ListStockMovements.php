<?php

namespace App\Filament\Resources\StockMovements\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\InventoryActions;
use Filament\Resources\Pages\ListRecords;

class ListStockMovements extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = StockMovementResource::class;

    protected function helpManual(): string
    {
        return 'movimientos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), InventoryActions::movement()];
    }
}
