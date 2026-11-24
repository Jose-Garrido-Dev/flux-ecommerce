<?php

namespace App\Filament\Resources\Coupons\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCoupons extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = CouponResource::class;

    protected function helpManual(): string
    {
        return 'cupones';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            CreateAction::make()->using(fn (array $data) => CouponResource::persist(null, $data)),
        ];
    }
}
