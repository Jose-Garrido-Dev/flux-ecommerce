<?php

namespace App\Filament\Resources\Shipments;

use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentEvent;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Services\RateEngine;
use App\Domain\Shipping\Services\ShipmentService;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Filament\Resources\Shipments\Pages\ViewShipment;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Logística → Despachos: todos los envíos y retiros, con su seguimiento e incidencias. */
class ShipmentResource extends Resource
{
    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 1;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'despacho';

    protected static ?string $pluralModelLabel = 'Despachos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->with(['order.shippingLine', 'order.items', 'items.orderItem', 'events.user', 'incidents']);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'delivered', 'picked_up' => 'success',
            'failed_attempt', 'returned' => 'danger',
            'preparing', 'ready_for_pickup' => 'warning',
            'cancelled' => 'gray',
            default => 'info',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Productos')->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->label('Productos')->columns(3)->schema([
                        TextEntry::make('orderItem.product_name')->label('Producto')->columnSpan(2)
                            ->belowContent(fn ($record) => trim(($record->orderItem?->variant_label ? $record->orderItem->variant_label.' · ' : '').'SKU '.$record->orderItem?->sku)),
                        TextEntry::make('quantity')->label('Cantidad'),
                    ]),
                ]),
                Section::make('Seguimiento')->schema([
                    RepeatableEntry::make('events')->hiddenLabel()->label('Seguimiento')->columns(4)->schema([
                        TextEntry::make('occurred_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn ($state) => Shipment::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                        TextEntry::make('description')->label('Detalle')->placeholder('—')
                            ->belowContent(fn (ShipmentEvent $record) => $record->location),
                        TextEntry::make('source')->label('Registrado por')
                            ->formatStateUsing(fn ($state, ShipmentEvent $record) => $record->user?->name ?? (ShipmentEvent::SOURCES[$state] ?? $state)),
                    ]),
                ]),
                Section::make('Incidencias')->visible(fn (Shipment $record) => $record->incidents()->exists())->schema([
                    RepeatableEntry::make('incidents')->hiddenLabel()->label('Incidencias')->columns(4)->schema([
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                        TextEntry::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ShippingIncident::TYPES[$state] ?? $state),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn ($state) => ShippingIncident::STATUSES[$state] ?? $state)
                            ->color(fn ($state) => $state === 'resolved' ? 'success' : 'danger'),
                        TextEntry::make('description')->label('Detalle')->belowContent(fn (ShippingIncident $record) => $record->resolution ? 'Solución: '.$record->resolution : null),
                    ]),
                ]),
            ]),
            Grid::make(1)->schema([
                Section::make('Despacho')->schema([
                    TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn ($state) => Shipment::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('order.number')->label('Pedido')
                        ->url(fn (Shipment $record) => OrderResource::getUrl('view', ['record' => $record->order])),
                    TextEntry::make('carrier_name')->label('Entrega'),
                    TextEntry::make('tracking_number')->label('N.º de seguimiento')->placeholder('Sin número')->copyable()
                        ->url(fn (Shipment $record) => app(ShipmentService::class)->trackingUrl($record), shouldOpenInNewTab: true),
                    TextEntry::make('packages')->label('Bultos')
                        ->belowContent(fn (Shipment $record) => $record->weight_g ? 'Peso '.RateEngine::kg($record->weight_g) : null),
                    TextEntry::make('cost_amount')->label('Costo del courier')->placeholder('—')->formatStateUsing(fn ($state) => OrderResource::money($state)),
                    TextEntry::make('notes')->label('Notas internas')->placeholder('—'),
                    TextEntry::make('shipped_at')->label('Despachado')->dateTime('d-m-Y H:i')->placeholder('—'),
                    TextEntry::make('delivered_at')->label('Entregado')->dateTime('d-m-Y H:i')->placeholder('—'),
                ]),
                Section::make('Destino')->schema([
                    TextEntry::make('address')->label('Dirección')->placeholder('Retiro en tienda')
                        ->state(fn (Shipment $record) => OrderResource::address($record->order)),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'incidents']))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('N.º de pedido o de seguimiento')
            ->columns([
                TextColumn::make('order.number')->label('Pedido')->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->whereHas('order', fn ($o) => $o->where('number', 'like', "%{$search}%"))
                        ->orWhere('tracking_number', 'like', "%{$search}%"))),
                TextColumn::make('created_at')->label('Creado')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('carrier_name')->label('Entrega')->description(fn (Shipment $r) => $r->tracking_number),
                TextColumn::make('destination')->label('Destino')
                    ->state(fn (Shipment $r) => ($a = $r->order->shipping_address) ? "{$a['commune']}, {$a['region']}" : 'Retiro en tienda'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => Shipment::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('open_incidents')->label('Incidencias')->badge()->color('danger')
                    ->state(fn (Shipment $r) => $r->incidents->where('status', '!=', 'resolved')->count() ?: null)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Shipment::STATUSES),
                SelectFilter::make('carrier_name')->label('Entrega')
                    ->options(fn () => Shipment::query()->distinct()->orderBy('carrier_name')->pluck('carrier_name', 'carrier_name')),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin despachos')
            ->emptyStateDescription('Crea el despacho desde el pedido (botón "Crear despacho") cuando esté pagado.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipments::route('/'),
            'view' => ViewShipment::route('/{record}'),
        ];
    }
}
