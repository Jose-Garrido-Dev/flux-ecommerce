<?php

namespace App\Filament\Resources\Warehouses;

use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Shipping\Models\Region;
use App\Filament\Resources\Warehouses\Pages\ManageWarehouses;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Inventario → Bodegas (módulo 6.4). */
class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Inventario';

    protected static ?int $navigationSort = 50;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'bodega';

    protected static ?string $pluralModelLabel = 'Bodegas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120)->placeholder('Bodega Quilicura'),
            TextInput::make('code')->label('Código')->required()->maxLength(20)->alphaDash()->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => strtoupper((string) $state))
                ->helperText('Corto y sin espacios, por ejemplo QUILICURA.'),
            TextInput::make('address')->label('Dirección')->maxLength(200)->columnSpanFull(),
            Select::make('region_id')->label('Región')->options(fn () => Region::query()->orderBy('position')->pluck('name', 'id'))->searchable(),
            TextInput::make('commune')->label('Comuna')->maxLength(80),
            TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
            Select::make('manager_user_id')->label('Responsable')->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            Select::make('status')->label('Estado')->options(Warehouse::STATUSES)->default('active')->required()
                ->helperText('El stock de una bodega inactiva no se ofrece en la tienda.'),
            TextInput::make('priority')->label('Prioridad')->numeric()->integer()->minValue(0)->default(0)
                ->helperText('Al reservar se usa primero la bodega principal y luego por prioridad (menor número primero).'),
            Toggle::make('is_default')->label('Bodega principal')
                ->helperText('Recibe el stock de los productos nuevos y de las importaciones. Solo puede haber una.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('region')
                ->withSum('levels as units', 'on_hand')->withSum('levels as reserved_units', 'reserved'))
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->description(fn (Warehouse $w) => $w->address),
                TextColumn::make('code')->label('Código'),
                TextColumn::make('region.name')->label('Región')->placeholder('—')->description(fn (Warehouse $w) => $w->commune),
                TextColumn::make('units')->label('Unidades')->numeric(thousandsSeparator: '.')->placeholder('0'),
                TextColumn::make('reserved_units')->label('Reservadas')->numeric(thousandsSeparator: '.')->placeholder('0'),
                TextColumn::make('priority')->label('Prioridad')->sortable(),
                IconColumn::make('is_default')->label('Principal')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => Warehouse::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Warehouse $record, array $data) => ManageWarehouses::save($record, $data)),
                DeleteAction::make()
                    ->using(function (Warehouse $record, DeleteAction $action) {
                        if ($reason = self::deletionBlocker($record)) {
                            Notification::make()->danger()->title('No se puede eliminar')->body($reason)->send();
                            $action->halt();
                        }
                        DB::transaction(function () use ($record) {
                            $record->levels()->delete();
                            $record->delete();
                        });

                        return true;
                    }),
            ]);
    }

    public static function deletionBlocker(Warehouse $warehouse): ?string
    {
        return match (true) {
            $warehouse->is_default => 'Es la bodega principal. Elige otra como principal antes de eliminarla.',
            $warehouse->levels()->where(fn ($q) => $q->where('on_hand', '!=', 0)->orWhere('reserved', '!=', 0))->exists() => 'Tiene stock. Transfiérelo a otra bodega o desactívala.',
            DB::table('stock_movements')->where('warehouse_id', $warehouse->id)->exists() => 'Tiene movimientos registrados. Desactívala para conservar el historial.',
            default => null,
        };
    }

    /** Deja una sola bodega principal y evita desactivar la principal. */
    public static function normalize(array $data, ?Warehouse $record = null): array
    {
        if (($data['is_default'] ?? false) && ($data['status'] ?? 'active') !== 'active') {
            $data['status'] = 'active';
        }
        if ($record?->is_default && empty($data['is_default'])) {
            $data['is_default'] = true; // la principal se cambia marcando otra
        }

        return $data;
    }

    public static function getPages(): array
    {
        return ['index' => ManageWarehouses::route('/')];
    }
}
