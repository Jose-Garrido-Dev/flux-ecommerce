<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = OrderResource::class;

    protected function helpManual(): string
    {
        return 'pedidos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
