<?php

namespace App\Filament\Resources\StockReservations\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\StockReservations\StockReservationResource;
use Filament\Resources\Pages\ListRecords;

class ListStockReservations extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = StockReservationResource::class;

    protected function helpManual(): string
    {
        return 'reservas';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
