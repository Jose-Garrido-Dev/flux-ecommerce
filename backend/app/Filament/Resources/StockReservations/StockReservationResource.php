<?php

namespace App\Filament\Resources\StockReservations;

use App\Domain\Inventory\Models\StockReservation;
use App\Domain\Inventory\Services\InventoryService;
use App\Filament\Resources\StockReservations\Pages\ListStockReservations;
use App\Filament\Support\InventoryActions;
use BackedEnum;
use Filament\Actions\Action;
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

/** Inventario → Reservas: stock apartado por carritos y pedidos (módulo 6.5). */
class StockReservationResource extends Resource
{
    protected static ?string $model = StockReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'stock-reservations';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'reserva';

    protected static ?string $pluralModelLabel = 'Reservas';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['variant.product', 'variant.attributeValues', 'warehouse']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Creada')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('product')->label('Producto')->wrap()
                    ->state(fn (StockReservation $r) => InventoryActions::variantLabel($r->variant)),
                TextColumn::make('warehouse.name')->label('Bodega')->placeholder('Sobreventa'),
                TextColumn::make('quantity')->label('Cantidad'),
                TextColumn::make('reference_type')->label('Origen')
                    ->formatStateUsing(fn (string $state) => StockReservation::REFERENCES[$state] ?? $state)
                    ->description(fn (StockReservation $r) => $r->reference_type === 'order' ? $r->reference_id : null),
                TextColumn::make('expires_at')->label('Vence')->dateTime('d-m-Y H:i')->sortable()
                    ->color(fn (StockReservation $r) => $r->status === 'active' && $r->expires_at?->isPast() ? 'danger' : null),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => StockReservation::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'warning', 'consumed' => 'success', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(StockReservation::STATUSES)->default('active'),
                SelectFilter::make('reference_type')->label('Origen')->options(StockReservation::REFERENCES),
                Filter::make('overdue')->label('Vencidas sin liberar')
                    ->query(fn (Builder $query) => $query->where('status', 'active')->where('expires_at', '<', now())),
            ])
            ->recordActions([
                Action::make('release')->label('Liberar')->icon(Heroicon::OutlinedLockOpen)->color('warning')
                    ->visible(fn (StockReservation $r) => $r->status === 'active')
                    ->authorize(fn () => auth()->user()?->can('inventory.update'))
                    ->modalHeading('Liberar reserva')
                    ->modalDescription('Las unidades vuelven a estar disponibles para la venta. Si el cliente aún está comprando, su carrito perderá la reserva.')
                    ->modalSubmitActionLabel('Liberar')
                    ->schema([TextInput::make('reason')->label('Motivo')->required()->maxLength(255)])
                    ->action(function (StockReservation $record, array $data) {
                        app(InventoryService::class)->release($record, 'released', $data['reason']);
                        Notification::make()->success()->title('Reserva liberada')->send();
                    }),
            ])
            ->emptyStateHeading('Sin reservas')
            ->emptyStateDescription('Las reservas se crean cuando un cliente agrega productos al carrito o compra (desde la Fase 5).');
    }

    public static function getPages(): array
    {
        return ['index' => ListStockReservations::route('/')];
    }
}
