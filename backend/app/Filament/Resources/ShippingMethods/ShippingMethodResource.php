<?php

namespace App\Filament\Resources\ShippingMethods;

use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Region;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShippingMethods\Pages\ManageShippingMethods;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Logística → Métodos de envío: tarifa fija, tarifas por zona y peso (despacho propio o courier) y retiro en tienda. */
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
        $type = fn (Get $get) => $get('type');
        $kg = fn (TextInput $c) => $c->numeric()->minValue(0)->step(0.001)->suffix('kg')
            ->formatStateUsing(fn ($state) => $state === null ? null : round($state / 1000, 3))
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 1000));

        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre que ve el cliente')->required()->maxLength(120)->placeholder('Despacho a domicilio'),
            TextInput::make('code')->label('Código')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->dehydrateStateUsing(fn (?string $state) => strtolower((string) $state))->placeholder('domicilio-rm'),
            Select::make('type')->label('Tipo')->options(ShippingMethod::TYPES)->required()->live()->default('flat'),
            Select::make('status')->label('Estado')->options(ShippingMethod::STATUSES)->required()->default('active'),
            Select::make('carrier_code')->label('¿Quién entrega?')->placeholder('Despacho propio (vehículo de la tienda)')
                ->options(fn () => Courier::query()->orderBy('name')->pluck('name', 'code'))
                ->helperText('Elige el courier si los despachos de este método salen con uno. Se propone al crear el despacho del pedido.')
                ->visible(fn (Get $get) => $type($get) !== 'pickup'),
            TextInput::make('amount')->label('Precio (IVA incluido)')->prefix('$')->integer()->minValue(0)->required()->default(0)
                ->visible(fn (Get $get) => $type($get) !== 'table'),
            TextInput::make('free_over')->label('Gratis desde')->prefix('$')->integer()->minValue(1)
                ->helperText('Opcional. Si el subtotal llega a este monto, el envío no se cobra.'),
            Select::make('region_ids')->label('Regiones donde se ofrece')->multiple()->columnSpanFull()
                ->options(fn () => Region::query()->orderBy('position')->pluck('name', 'id'))
                ->helperText('Vacío = todo Chile. Para cobrar según la comuna o el peso usa el tipo "Tarifas por zona y peso".')
                ->visible(fn (Get $get) => $type($get) === 'flat'),
            Section::make('Peso y restricciones')->columns(3)->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) !== 'pickup')
                ->schema([
                    TextInput::make('volumetric_factor')->label('Factor volumétrico')->integer()->minValue(1000)->maxValue(10000)->suffix('cm³/kg')
                        ->placeholder('4000')->helperText('Si lo completas, cada unidad cobra el mayor entre su peso real y largo × ancho × alto ÷ factor. Vacío = solo peso real.'),
                    $kg(TextInput::make('max_weight_g')->label('Peso máximo del pedido'))->helperText('Vacío = sin límite.'),
                    Toggle::make('supports_cold_chain')->label('Despacha con cadena de frío')->inline(false)
                        ->helperText('Los productos marcados "Requiere cadena de frío" solo se ofrecen con métodos que la tengan.'),
                ]),
            Repeater::make('rates')->label('Tarifas')->relationship()->columnSpanFull()->orderColumn('position')
                ->visible(fn (Get $get) => $type($get) === 'table')
                ->addActionLabel('Agregar tarifa')->defaultItems(1)->columns(5)
                ->helperText('Para cada zona, un precio por rango de peso. Ambos límites se incluyen: "0 a 5 kg" cubre exactamente 5 kg. Las tarifas sin zona valen para cualquier zona que no tenga una propia.')
                ->schema([
                    Select::make('zone_id')->label('Zona')->placeholder('Cualquier zona')
                        ->options(fn () => ShippingZone::query()->orderBy('priority')->pluck('name', 'id')),
                    $kg(TextInput::make('min_weight_g')->label('Desde'))->required()->default(0),
                    $kg(TextInput::make('max_weight_g')->label('Hasta'))->helperText('Vacío = sin límite'),
                    TextInput::make('amount')->label('Precio')->prefix('$')->integer()->minValue(0)->required(),
                    TextInput::make('estimated_days')->label('Plazo')->maxLength(40)->placeholder('1 a 2 días hábiles'),
                ]),
            Section::make('Retiro en tienda')->columns(2)->columnSpanFull()
                ->visible(fn (Get $get) => $type($get) === 'pickup')
                ->schema([
                    TextInput::make('pickup_address')->label('Dirección de retiro')->maxLength(255)->columnSpanFull()
                        ->helperText('Vacío = la dirección de Configuración → Datos del negocio. Crea un método por cada local de retiro.'),
                    TextInput::make('pickup_hours')->label('Horario')->maxLength(255)->placeholder('Lunes a viernes de 10:00 a 19:00'),
                    TextInput::make('pickup_instructions')->label('Instrucciones')->maxLength(500)->placeholder('Presenta tu número de pedido y tu carnet'),
                ]),
            TextInput::make('estimated_days')->label('Plazo estimado')->maxLength(40)->placeholder('2 a 4 días hábiles')
                ->helperText(fn (Get $get) => $type($get) === 'table' ? 'Se usa si la tarifa no tiene plazo propio.' : null),
            TextInput::make('position')->label('Orden')->integer()->minValue(0)->default(0),
            TextInput::make('description')->label('Descripción')->maxLength(255)->columnSpanFull()
                ->helperText('Se muestra bajo el nombre en el checkout.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('courier')->withCount('rates'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Nombre')->description(fn (ShippingMethod $r) => $r->description),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ShippingMethod::TYPES[$state] ?? $state),
                TextColumn::make('delivery')->label('Entrega')->state(fn (ShippingMethod $r) => $r->deliveryLabel()),
                TextColumn::make('amount')->label('Precio')
                    ->state(fn (ShippingMethod $r) => $r->type === 'table' ? $r->rates_count.($r->rates_count === 1 ? ' tarifa' : ' tarifas') : ($r->amount ? OrderResource::money($r->amount) : 'Gratis'))
                    ->description(fn (ShippingMethod $r) => $r->free_over ? 'Gratis desde '.OrderResource::money($r->free_over) : null),
                TextColumn::make('regions')->label('Cobertura')
                    ->state(fn (ShippingMethod $r) => $r->type === 'pickup' ? ($r->pickup_address ?: 'Dirección del negocio') : ($r->type === 'table' ? 'Según zonas' : (empty($r->region_ids) ? 'Todo Chile'
                        : Region::query()->whereIn('id', $r->region_ids)->orderBy('position')->pluck('name')->implode(', '))))->wrap(),
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
