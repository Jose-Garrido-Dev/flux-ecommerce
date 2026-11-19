<?php

namespace App\Filament\Resources\Warehouses\Pages;

use App\Domain\Inventory\Models\Warehouse;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Warehouses\WarehouseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\DB;

class ManageWarehouses extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = WarehouseResource::class;

    protected function helpManual(): string
    {
        return 'bodegas';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            CreateAction::make()->using(fn (array $data) => self::save(new Warehouse, $data)),
        ];
    }

    public static function save(Warehouse $warehouse, array $data): Warehouse
    {
        $data = WarehouseResource::normalize($data, $warehouse->exists ? $warehouse : null);

        return DB::transaction(function () use ($warehouse, $data) {
            if ($data['is_default'] ?? false) {
                Warehouse::query()->whereKeyNot($warehouse->id ?? 0)->where('is_default', true)->update(['is_default' => false]);
            }
            $warehouse->fill($data)->save();

            return $warehouse;
        });
    }
}
