<?php

namespace App\Filament\Resources\StockTransfers\Pages;

use App\Domain\Inventory\Services\InventoryService;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected static ?string $title = 'Nueva transferencia';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(InventoryService::class)->transfer(
                (int) $data['from_warehouse_id'], (int) $data['to_warehouse_id'],
                array_values($data['items'] ?? []), $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('No se pudo transferir')->body(collect($e->errors())->flatten()->implode(' '))->send();
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ['data.'.$k => $m])->all());
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Transferencia registrada';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
