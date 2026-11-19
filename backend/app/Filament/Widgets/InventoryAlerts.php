<?php

namespace App\Filament\Widgets;

use App\Domain\Inventory\Services\InventoryReport;
use App\Filament\Resources\InventoryLevels\InventoryLevelResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockReservations\StockReservationResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/** Alertas de inventario (módulo 6.3). Cada tarjeta abre la lista filtrada. */
class InventoryAlerts extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Alertas de inventario';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('inventory.view') ?? false;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $report = app(InventoryReport::class);
        $count = fn ($query) => DB::query()->fromSub($query, 'x')->count();
        $levels = fn (string $state) => InventoryLevelResource::getUrl('index', ['filters' => ['state' => ['value' => $state]]]);

        $out = $count($report->outOfStock());
        $low = $count($report->lowStock());
        $stale = $count($report->withoutMovement(90));
        $overdue = $report->overdueReservations();
        $differences = $report->countDifferences(30);
        $backorder = $report->backorderCount();
        $top = $report->topRotation(30);

        return [
            Stat::make('Sin stock', $out)->description($out ? 'Productos que no se pueden comprar' : 'Todo con stock')
                ->color($out ? 'danger' : 'success')->url($levels('out')),
            Stat::make('Stock bajo', $low)->description('En o bajo su stock mínimo')
                ->color($low ? 'warning' : 'success')->url($levels('low')),
            Stat::make('Reservas vencidas sin liberar', $overdue)
                ->description($overdue ? 'Revisa que el programador de tareas esté corriendo' : 'Las reservas se liberan a tiempo')
                ->color($overdue ? 'danger' : 'success')
                ->url(StockReservationResource::getUrl('index', ['filters' => ['overdue' => ['isActive' => true], 'status' => ['value' => null]]])),
            Stat::make('Sin movimientos en 90 días', $stale)->description('Con stock pero sin ventas ni ingresos')
                ->color($stale ? 'warning' : 'gray')->url($levels('stale')),
            Stat::make('Diferencias de inventario', $differences)->description('Ajustes por conteo en 30 días')
                ->color($differences ? 'warning' : 'gray')
                ->url(StockMovementResource::getUrl('index', ['filters' => ['type' => ['values' => ['adjust_in', 'adjust_out']]]])),
            Stat::make('Mayor rotación', $top ? $top['units'].' u.' : '—')
                ->description($top ? $top['sku'].' en 30 días' : 'Aún no hay ventas'),
            Stat::make('Con sobreventa permitida', $backorder)->description('Se venden aunque no haya stock')
                ->color($backorder ? 'info' : 'gray')->url($levels('backorder')),
        ];
    }
}
