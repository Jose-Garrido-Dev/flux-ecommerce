<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\MovementType;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Consultas para alertas y filtros de inventario (módulo 6.3).
 * Todas trabajan por variante y suman las bodegas activas.
 * Solo cuentan variantes activas que controlan stock.
 */
class InventoryReport
{
    /** Disponible por variante: variant_id, available, on_hand, min_stock, allow_backorder. */
    public function availability(): Builder
    {
        return DB::table('product_variants as v')
            ->join('inventory_items as i', 'i.variant_id', '=', 'v.id')
            ->leftJoin('inventory_levels as l', 'l.variant_id', '=', 'v.id')
            ->leftJoin('warehouses as w', function ($j) {
                $j->on('w.id', '=', 'l.warehouse_id')->where('w.status', 'active');
            })
            ->where('v.status', 'active')
            ->where('i.track_stock', true)
            ->groupBy('v.id', 'i.min_stock', 'i.allow_backorder')
            ->selectRaw('v.id as variant_id, i.min_stock, i.allow_backorder,
                COALESCE(SUM(CASE WHEN w.id IS NOT NULL THEN l.on_hand - l.reserved ELSE 0 END), 0) as available,
                COALESCE(SUM(CASE WHEN w.id IS NOT NULL THEN l.on_hand ELSE 0 END), 0) as on_hand');
    }

    /** Ids de variantes sin stock disponible (y sin sobreventa). */
    public function outOfStock(): Builder
    {
        return DB::query()->fromSub($this->availability(), 'a')->where('a.available', '<=', 0)
            ->where('a.allow_backorder', false)->select('a.variant_id');
    }

    /** Ids de variantes con stock, pero en o bajo su stock mínimo. */
    public function lowStock(): Builder
    {
        return DB::query()->fromSub($this->availability(), 'a')->where('a.available', '>', 0)
            ->where('a.min_stock', '>', 0)->whereColumn('a.available', '<=', 'a.min_stock')->select('a.variant_id');
    }

    /** Ids de variantes con stock físico que no tuvieron movimientos de stock en N días. */
    public function withoutMovement(int $days = 90): Builder
    {
        return DB::query()->fromSub($this->availability(), 'a')->where('a.on_hand', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_movements as m')
                ->whereColumn('m.variant_id', 'a.variant_id')->where('m.quantity', '!=', 0)
                ->where('m.created_at', '>=', now()->subDays($days)))
            ->select('a.variant_id');
    }

    public function backorderCount(): int
    {
        return DB::table('inventory_items')->where('track_stock', true)->where('allow_backorder', true)->count();
    }

    /** Reservas activas que ya debían estar liberadas (señal de que el scheduler no está corriendo). */
    public function overdueReservations(): int
    {
        return DB::table('stock_reservations')->where('status', 'active')
            ->where('expires_at', '<', now()->subMinutes(5))->count();
    }

    /** Ajustes por conteo en los últimos N días (diferencias entre sistema y conteo físico). */
    public function countDifferences(int $days = 30): int
    {
        return DB::table('stock_movements')->where('reference_type', 'count')
            ->whereIn('type', [MovementType::AdjustIn->value, MovementType::AdjustOut->value])
            ->where('created_at', '>=', now()->subDays($days))->count();
    }

    /** Variante más vendida en los últimos N días: [sku, unidades] o null. */
    public function topRotation(int $days = 30): ?array
    {
        $row = DB::table('stock_movements as m')->join('product_variants as v', 'v.id', '=', 'm.variant_id')
            ->where('m.type', MovementType::SaleOut->value)->where('m.created_at', '>=', now()->subDays($days))
            ->groupBy('v.sku')->selectRaw('v.sku, -SUM(m.quantity) as units')->orderByDesc('units')->first();

        return $row ? ['sku' => $row->sku, 'units' => (int) $row->units] : null;
    }
}
