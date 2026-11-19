<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Products\Concerns\SavesProduct;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    use HasHelpManual, SavesProduct;

    protected static string $resource = ProductResource::class;

    protected function helpManual(): string
    {
        return 'productos';
    }

    protected function afterCreate(): void
    {
        $this->keepPrimaryCategoryAttached();
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveProduct(null, $data);
    }

    protected function getRedirectUrl(): string
    {
        // Después de crear se sigue editando: ahí están las pestañas de imágenes y variantes
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
