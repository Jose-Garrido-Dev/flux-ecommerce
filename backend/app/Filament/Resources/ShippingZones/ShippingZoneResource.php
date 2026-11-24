<?php

namespace App\Filament\Resources\ShippingZones;

use App\Domain\Shipping\Models\Commune;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\ShippingZones\Pages\ManageShippingZones;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Logística → Zonas de envío: agrupan regiones y comunas para cobrar distinto según el destino. */
class ShippingZoneResource extends Resource
{
    protected static ?string $model = ShippingZone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 11;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'zona de envío';

    protected static ?string $pluralModelLabel = 'Zonas de envío';

    public static function form(Schema $schema): Schema
    {
        $communes = fn () => Commune::query()->orderBy('name')->pluck('name')->all();

        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120)->placeholder('Santiago urbano'),
            TextInput::make('priority')->label('Prioridad')->integer()->minValue(0)->required()->default(100)
                ->helperText('Si una comuna está en dos zonas, gana la de número menor.'),
            Select::make('region_ids')->label('Regiones completas')->multiple()->columnSpanFull()
                ->options(fn () => Region::query()->orderBy('position')->pluck('name', 'id'))
                ->helperText('Todas las comunas de estas regiones, salvo las excluidas abajo.'),
            TagsInput::make('communes')->label('Comunas sueltas')->columnSpanFull()->suggestions($communes)
                ->placeholder('Escribe una comuna y presiona Enter')
                ->helperText('Comunas que también pertenecen a la zona aunque su región no esté arriba. Se comparan sin importar tildes ni mayúsculas.'),
            TagsInput::make('excluded_communes')->label('Comunas excluidas')->columnSpanFull()->suggestions($communes)
                ->placeholder('Escribe una comuna y presiona Enter')
                ->helperText('Comunas de las regiones elegidas que NO pertenecen a esta zona (por ejemplo, zonas rurales o islas).'),
            Select::make('status')->label('Estado')->options(['active' => 'Activa', 'inactive' => 'Inactiva'])->required()->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('regions')->label('Regiones')->wrap()
                    ->state(fn (ShippingZone $r) => empty($r->region_ids) ? '—'
                        : Region::query()->whereIn('id', $r->region_ids)->orderBy('position')->pluck('name')->implode(', ')),
                TextColumn::make('communes')->label('Comunas sueltas')->state(fn (ShippingZone $r) => implode(', ', $r->communes ?? []) ?: '—')->wrap(),
                TextColumn::make('excluded_communes')->label('Excluidas')->state(fn (ShippingZone $r) => implode(', ', $r->excluded_communes ?? []) ?: '—')->wrap(),
                TextColumn::make('priority')->label('Prioridad')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => $state === 'active' ? 'Activa' : 'Inactiva')
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(function (ShippingZone $record, DeleteAction $action) {
                    if ($record->rates()->exists()) {
                        Notification::make()->danger()->title('No se puede eliminar')->body('Hay tarifas que usan esta zona. Quítalas de los métodos de envío o desactiva la zona.')->send();
                        $action->halt();
                    }
                    $record->delete();

                    return true;
                }),
            ])
            ->emptyStateHeading('Sin zonas de envío')
            ->emptyStateDescription('Crea zonas para cobrar distinto según la comuna de destino (métodos "Tarifas por zona y peso").');
    }

    public static function getPages(): array
    {
        return ['index' => ManageShippingZones::route('/')];
    }
}
