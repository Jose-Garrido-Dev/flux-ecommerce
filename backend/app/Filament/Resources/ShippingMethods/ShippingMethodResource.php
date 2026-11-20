<?php

namespace App\Filament\Resources\ShippingMethods;

use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Logística → Métodos de envío. Fase 5: tarifa fija por región y retiro en tienda. Zonas y couriers en la Fase 7. */
class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 10;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'método de envío';

    protected static ?string $pluralModelLabel = 'Métodos de envío';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre que ve el cliente')->required()->maxLength(120)->placeholder('Despacho a domicilio'),
            TextInput::make('code')->label('Código')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => strtolower((string) $state))->placeholder('domicilio-rm'),
            Select::make('type')->label('Tipo')->options(ShippingMethod::TYPES)->required()->live()->default('flat'),
            Select::make('status')->label('Estado')->options(ShippingMethod::STATUSES)->required()->default('active'),
            TextInput::make('amount')->label('Precio (IVA incluido)')->prefix('$')->integer()->minValue(0)->required()->default(0)
                ->visible(fn (Get $get) => $get('type') === 'flat'),
            TextInput::make('free_over')->label('Gratis desde')->prefix('$')->integer()->minValue(1)
                ->helperText('Opcional. Si el subtotal llega a este monto, el envío no se cobra.')
                ->visible(fn (Get $get) => $get('type') === 'flat'),
            Select::make('region_ids')->label('Regiones donde se ofrece')->multiple()->columnSpanFull()
                ->options(fn () => Region::query()->orderBy('position')->pluck('name', 'id'))
                ->helperText('Vacío = todo Chile. Para cobrar distinto por región crea un método por cada grupo de regiones.')
                ->visible(fn (Get $get) => $get('type') === 'flat'),
            TextInput::make('estimated_days')->label('Plazo estimado')->maxLength(40)->placeholder('2 a 4 días hábiles'),
            TextInput::make('position')->label('Orden')->integer()->minValue(0)->default(0),
            TextInput::make('description')->label('Descripción')->maxLength(255)->columnSpanFull()
                ->helperText('Se muestra bajo el nombre en el checkout. En el retiro, la dirección sale de Configuración → Datos del negocio.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Nombre')->description(fn (ShippingMethod $r) => $r->description),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ShippingMethod::TYPES[$state] ?? $state),
                TextColumn::make('amount')->label('Precio')->formatStateUsing(fn ($state, ShippingMethod $r) => $r->type === 'pickup' || ! $state ? 'Gratis' : OrderResource::money($state))
                    ->description(fn (ShippingMethod $r) => $r->free_over ? 'Gratis desde '.OrderResource::money($r->free_over) : null),
                TextColumn::make('regions')->label('Regiones')
                    ->state(fn (ShippingMethod $r) => $r->type === 'pickup' ? '—' : (empty($r->region_ids) ? 'Todo Chile'
                        : Region::query()->whereIn('id', $r->region_ids)->orderBy('position')->pluck('name')->implode(', ')))->wrap(),
                TextColumn::make('estimated_days')->label('Plazo')->placeholder('—'),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => ShippingMethod::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->using(function (ShippingMethod $record, DeleteAction $action) {
                    if (DB::table('order_shipping_lines')->where('method_id', $record->id)->exists()) {
                        Notification::make()->danger()->title('No se puede eliminar')->body('Ya se usó en pedidos. Desactívalo para dejar de ofrecerlo.')->send();
                        $action->halt();
                    }
                    $record->delete();

                    return true;
                }),
            ])
            ->emptyStateHeading('Sin métodos de envío')
            ->emptyStateDescription('Crea al menos uno para que la tienda pueda vender.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageShippingMethods::route('/')];
    }
}
