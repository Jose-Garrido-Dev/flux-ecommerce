<?php

namespace App\Filament\Resources\InventoryLevels;

use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryReport;
use App\Filament\Resources\InventoryLevels\Pages\ListInventoryLevels;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Support\InventoryActions;
use App\Filament\Support\VariantPicker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Inventario → Existencias: stock por producto, variante y bodega (módulo 6.1). Solo lectura: se cambia con movimientos. */
class InventoryLevelResource extends Resource
{
    protected static ?string $model = InventoryLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'inventory';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'existencia';

    protected static ?string $pluralModelLabel = 'Existencias';

    public const STATES = [
        'out' => 'Sin stock',
        'low' => 'Stock bajo',
        'ok' => 'Con stock',
        'backorder' => 'Con sobreventa',
        'untracked' => 'Sin control de stock',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['variant.product', 'variant.attributeValues', 'variant.inventoryItem', 'warehouse']))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('variant.product.name')->label('Producto')->wrap()
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereIn('variant_id', array_keys(VariantPicker::search($search, 500)))),
                TextColumn::make('options')->label('Variante')->placeholder('Única')
                    ->state(fn (InventoryLevel $r) => $r->variant->attributeValues->pluck('label')->implode(' / ') ?: null),
                TextColumn::make('variant.sku')->label('SKU')->copyable(),
                TextColumn::make('warehouse.name')->label('Bodega'),
                TextColumn::make('on_hand')->label('Físico')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('reserved')->label('Reservado')->numeric(thousandsSeparator: '.')->sortable(),
                TextColumn::make('available')->label('Disponible')->weight('bold')
                    ->state(fn (InventoryLevel $r) => $r->available())->numeric(thousandsSeparator: '.'),
                TextColumn::make('min')->label('Mínimo')->state(fn (InventoryLevel $r) => $r->variant->inventoryItem?->min_stock ?: null)->placeholder('—'),
                TextColumn::make('state')->label('Estado')->badge()
                    ->state(fn (InventoryLevel $r) => self::STATES[self::state($r)])
                    ->color(fn (InventoryLevel $r) => match (self::state($r)) {
                        'out' => 'danger', 'low' => 'warning', 'ok' => 'success', 'backorder' => 'info', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('warehouse_id')->label('Bodega')
                    ->options(fn () => Warehouse::query()->orderBy('priority')->pluck('name', 'id')),
                SelectFilter::make('state')->label('Estado')
                    ->options(['out' => 'Sin stock', 'low' => 'Stock bajo', 'stale' => 'Sin movimientos en 90 días', 'backorder' => 'Con sobreventa'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'out' => $query->whereIn('variant_id', app(InventoryReport::class)->outOfStock()),
                        'low' => $query->whereIn('variant_id', app(InventoryReport::class)->lowStock()),
                        'stale' => $query->whereIn('variant_id', app(InventoryReport::class)->withoutMovement(90)),
                        'backorder' => $query->whereHas('variant.inventoryItem', fn ($i) => $i->where('track_stock', true)->where('allow_backorder', true)),
                        default => $query,
                    }),
                TernaryFilter::make('tracked')->label('Control de stock')
                    ->trueLabel('Controla stock')->falseLabel('No controla stock')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('variant.inventoryItem', fn ($i) => $i->where('track_stock', true)),
                        false: fn (Builder $query) => $query->whereHas('variant.inventoryItem', fn ($i) => $i->where('track_stock', false)),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    InventoryActions::movement('movement', fn (InventoryLevel $r) => [$r->variant_id, $r->warehouse_id])->label('Movimiento'),
                    InventoryActions::count(),
                    InventoryActions::configure(),
                    Action::make('history')->label('Historial')->icon(Heroicon::OutlinedClock)
                        ->url(fn (InventoryLevel $r) => StockMovementResource::getUrl('index', ['filters' => ['variant_id' => ['value' => $r->variant_id]]])),
                ])->label('Acciones')->button()->size('sm'),
            ])
            ->emptyStateHeading('Sin existencias')
            ->emptyStateDescription('Cuando crees productos aparecerán aquí con su stock por bodega.');
    }

    public static function state(InventoryLevel $level): string
    {
        $item = $level->variant->inventoryItem;
        if (! $item || ! $item->track_stock) {
            return 'untracked';
        }
        $available = $level->available();

        return match (true) {
            $available <= 0 => $item->allow_backorder ? 'backorder' : 'out',
            $item->min_stock > 0 && $available <= $item->min_stock => 'low',
            default => 'ok',
        };
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryLevels::route('/')];
    }
}
