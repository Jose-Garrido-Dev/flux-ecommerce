<?php

namespace App\Filament\Resources\StockMovements;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Orders\Models\Order;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Support\InventoryActions;
use App\Filament\Support\VariantPicker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Inventario → Movimientos (módulo 6.2). Historial inmutable: se corrige con "Revertir". */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 20;

    protected static ?string $slug = 'stock-movements';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'movimiento';

    protected static ?string $pluralModelLabel = 'Movimientos';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'variant.attributeValues', 'warehouse', 'user', 'reversal']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('type')->label('Tipo')->badge()
                    ->formatStateUsing(fn (MovementType $state) => $state->label())
                    ->color(fn (StockMovement $r) => $r->quantity > 0 ? 'success' : ($r->quantity < 0 ? 'danger' : 'gray')),
                TextColumn::make('product')->label('Producto')->wrap()
                    ->state(fn (StockMovement $r) => InventoryActions::variantLabel($r->variant)),
                TextColumn::make('warehouse.name')->label('Bodega'),
                TextColumn::make('quantity')->label('Cantidad')
                    ->formatStateUsing(fn (int $state) => ($state > 0 ? '+' : '').number_format($state, 0, ',', '.')),
                TextColumn::make('before_on_hand')->label('Antes'),
                TextColumn::make('after_on_hand')->label('Después'),
                TextColumn::make('reserved_change')->label('Reserva')->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (int $state) => $state === 0 ? '—' : ($state > 0 ? '+' : '').$state),
                TextColumn::make('reason')->label('Motivo')->wrap()->placeholder('—')
                    ->description(fn (StockMovement $r) => $r->reversal ? 'Revertido' : null),
                TextColumn::make('reference')->label('Referencia')->placeholder('—')
                    ->state(fn (StockMovement $r) => self::reference($r)),
                TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema'),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(MovementType::options())->multiple(),
                SelectFilter::make('warehouse_id')->label('Bodega')
                    ->options(fn () => Warehouse::query()->orderBy('priority')->pluck('name', 'id')),
                SelectFilter::make('variant_id')->label('Producto')->searchable()
                    ->getSearchResultsUsing(fn (string $search) => VariantPicker::search($search))
                    ->getOptionLabelUsing(fn ($value) => InventoryActions::variantLabel(ProductVariant::query()->find($value))),
                Filter::make('dates')->label('Fechas')
                    ->schema([
                        DatePicker::make('from')->label('Desde'),
                        DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('reverse')->label('Revertir')->icon(Heroicon::OutlinedArrowUturnLeft)->color('warning')
                    ->visible(fn (StockMovement $r) => $r->isReversible())
                    ->authorize(fn () => auth()->user()?->can('inventory.create'))
                    ->modalHeading('Revertir movimiento')
                    ->modalDescription('Se registra un contramovimiento que anula este. El movimiento original no se modifica.')
                    ->modalSubmitActionLabel('Revertir')
                    ->schema([TextInput::make('reason')->label('Motivo')->required()->maxLength(255)->placeholder('Cantidad mal ingresada')])
                    ->action(function (StockMovement $record, array $data) {
                        InventoryActions::run(fn () => app(InventoryService::class)->reverse($record, $data['reason']));
                        Notification::make()->success()->title('Movimiento revertido')->send();
                    }),
            ])
            ->emptyStateHeading('Sin movimientos')
            ->emptyStateDescription('Cada entrada, salida, ajuste o transferencia queda registrada aquí.');
    }

    /** @var array<string, int|string> número visible de cada pedido (uuid → número) */
    private static array $orderNumbers = [];

    public static function reference(StockMovement $movement): ?string
    {
        return match ($movement->reference_type) {
            null => null,
            'count' => 'Conteo físico',
            'transfer' => 'Transferencia #'.$movement->reference_id,
            'movement' => 'Revierte el movimiento #'.$movement->reference_id,
            'cart' => 'Carrito',
            'order' => 'Pedido '.(self::$orderNumbers[$movement->reference_id] ??= Order::query()->where('uuid', $movement->reference_id)->value('number') ?? '(no encontrado)'),
            'import' => 'Importación',
            'document' => $movement->reference_id,
            default => $movement->reference_id,
        };
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
