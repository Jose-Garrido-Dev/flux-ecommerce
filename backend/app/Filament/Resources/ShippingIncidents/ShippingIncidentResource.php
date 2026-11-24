<?php

namespace App\Filament\Resources\ShippingIncidents;

use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Services\ShipmentService;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ShippingIncidents\Pages\ManageShippingIncidents;
use App\Filament\Support\InventoryActions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Logística → Incidencias: atrasos, daños, extravíos y direcciones erróneas, con responsable y solución. */
class ShippingIncidentResource extends Resource
{
    protected static ?string $model = ShippingIncident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'Incidencias';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ShippingIncident::query()->where('status', '!=', 'resolved')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Incidencias sin resolver';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'shipment', 'owner']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('order.number')->label('Pedido')->weight('bold')
                    ->url(fn (ShippingIncident $record) => OrderResource::getUrl('view', ['record' => $record->order])),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ShippingIncident::TYPES[$state] ?? $state)
                    ->description(fn (ShippingIncident $record) => $record->shipment?->carrier_name),
                TextColumn::make('description')->label('Detalle')->limit(80)->wrap()
                    ->description(fn (ShippingIncident $record) => $record->resolution ? 'Solución: '.$record->resolution : null),
                TextColumn::make('owner.name')->label('Responsable')->placeholder('Sin asignar'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => ShippingIncident::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'resolved' => 'success',
                        'in_progress' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(ShippingIncident::STATUSES)->default('open'),
                SelectFilter::make('type')->label('Tipo')->options(ShippingIncident::TYPES),
            ])
            ->recordActions([
                Action::make('manage')->label('Gestionar')->icon(Heroicon::OutlinedPencilSquare)
                    ->authorize(fn () => auth()->user()?->can('shipping.dispatch'))
                    ->modalHeading('Gestionar la incidencia')
                    ->modalSubmitActionLabel('Guardar')
                    ->fillForm(fn (ShippingIncident $record) => $record->only(['status', 'resolution', 'owner_user_id']))
                    ->schema([
                        Select::make('status')->label('Estado')->options(ShippingIncident::STATUSES)->required()->live(),
                        Select::make('owner_user_id')->label('Responsable')->placeholder('Sin asignar')
                            ->options(fn () => User::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')),
                        Textarea::make('resolution')->label('Solución')->maxLength(2000)
                            ->required(fn ($get) => $get('status') === 'resolved'),
                    ])
                    ->action(function (array $data, ShippingIncident $record) {
                        $record->update(['owner_user_id' => $data['owner_user_id'] ?? null]);
                        InventoryActions::run(fn () => app(ShipmentService::class)->resolveIncident($record, $data['status'], $data['resolution'] ?? null));
                        Notification::make()->success()->title('Incidencia actualizada')->send();
                    }),
            ])
            ->emptyStateHeading('Sin incidencias')
            ->emptyStateDescription('Se registran desde el despacho con "Registrar incidencia".');
    }

    public static function getPages(): array
    {
        return ['index' => ManageShippingIncidents::route('/')];
    }
}
