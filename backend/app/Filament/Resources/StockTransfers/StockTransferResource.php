<?php

namespace App\Filament\Resources\StockTransfers;

use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\StockTransferItem;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;
use App\Filament\Resources\StockTransfers\Pages\ListStockTransfers;
use App\Filament\Resources\StockTransfers\Pages\ViewStockTransfer;
use App\Filament\Support\InventoryActions;
use App\Filament\Support\VariantPicker;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Inventario → Transferencias entre bodegas (módulo 6.2). Se registran completas: salida y entrada en el mismo momento. */
class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'stock-transfers';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'transferencia';

    protected static ?string $pluralModelLabel = 'Transferencias';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bodegas')->columns(2)->schema([
                InventoryActions::warehouseSelect('from_warehouse_id')->label('Desde'),
                InventoryActions::warehouseSelect('to_warehouse_id')->label('Hacia')->default(null)->different('from_warehouse_id'),
                Textarea::make('notes')->label('Notas')->maxLength(500)->rows(2)->columnSpanFull()
                    ->placeholder('Reposición de la tienda del centro'),
            ]),
            Section::make('Productos')->schema([
                Repeater::make('items')->label('Productos a transferir')->hiddenLabel()
                    ->addActionLabel('Agregar producto')->minItems(1)->defaultItems(1)->columns(3)
                    ->schema([
                        VariantPicker::make()->columnSpan(2),
                        TextInput::make('quantity')->label('Cantidad')->numeric()->integer()->minValue(1)->required(),
                    ]),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transferencia')->columns(3)->schema([
                TextEntry::make('from.name')->label('Desde'),
                TextEntry::make('to.name')->label('Hacia'),
                TextEntry::make('completed_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                TextEntry::make('user.name')->label('Usuario')->placeholder('Sistema'),
                TextEntry::make('notes')->label('Notas')->placeholder('—')->columnSpan(2),
            ]),
            Section::make('Productos')->schema([
                RepeatableEntry::make('items')->label('Productos')->hiddenLabel()->columns(2)->schema([
                    TextEntry::make('variant')->label('Producto')
                        ->state(fn (StockTransferItem $record) => InventoryActions::variantLabel($record->variant)),
                    TextEntry::make('quantity')->label('Cantidad'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['from', 'to', 'user'])->withSum('items as units', 'quantity')->withCount('items'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('N.º')->prefix('#'),
                TextColumn::make('completed_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('from.name')->label('Desde'),
                TextColumn::make('to.name')->label('Hacia'),
                TextColumn::make('items_count')->label('Productos'),
                TextColumn::make('units')->label('Unidades'),
                TextColumn::make('user.name')->label('Usuario')->placeholder('Sistema'),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin transferencias')
            ->emptyStateDescription('Usa "Nueva transferencia" para mover stock entre bodegas.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockTransfers::route('/'),
            'create' => CreateStockTransfer::route('/create'),
            'view' => ViewStockTransfer::route('/{record}'),
        ];
    }
}
