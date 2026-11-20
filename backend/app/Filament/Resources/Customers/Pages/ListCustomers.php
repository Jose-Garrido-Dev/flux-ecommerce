<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = CustomerResource::class;

    protected function helpManual(): string
    {
        return 'clientes';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }
}
