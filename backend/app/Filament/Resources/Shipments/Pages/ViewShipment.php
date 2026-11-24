<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Services\ShipmentService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Filament\Support\InventoryActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ViewShipment extends ViewRecord
{
    use HasHelpManual;

    protected static string $resource = ShipmentResource::class;

    public function getTitle(): string
    {
        return 'Despacho del pedido N.º '.$this->record->order->number;
    }

    protected function helpManual(): string
    {
        return 'despachos';
    }

    protected function getHeaderActions(): array
    {
        $can = fn () => auth()->user()?->can('shipping.dispatch');

        return [
            $this->helpManualAction(),
            Action::make('status')->label('Cambiar estado')->icon(Heroicon::OutlinedArrowRightCircle)->color('primary')
                ->visible(fn (Shipment $record) => ! $record->isFinal())
                ->authorize($can)
                ->modalHeading('Cambiar el estado del despacho')
                ->modalDescription('Al marcarlo Despachado, Listo para retiro o Entregado, el cliente recibe un correo.')
                ->modalSubmitActionLabel('Guardar')
                ->schema([
                    Select::make('status')->label('Nuevo estado')->required()
                        ->options(fn (Shipment $record) => collect(Shipment::TRANSITIONS[$record->status] ?? [])
                            ->mapWithKeys(fn ($s) => [$s => Shipment::STATUSES[$s]])
                            ->when($record->order->shippingLine?->type === 'pickup', fn ($c) => $c->except('shipped'))
                            ->when($record->order->shippingLine?->type !== 'pickup', fn ($c) => $c->except('ready_for_pickup'))),
                    TextInput::make('description')->label('Detalle para el cliente')->maxLength(255)->placeholder('Ej.: el courier retiró el paquete'),
                    TextInput::make('location')->label('Lugar')->maxLength(120)->placeholder('Ej.: centro de distribución Santiago'),
                    DateTimePicker::make('occurred_at')->label('Fecha y hora')->seconds(false)->default(now())->maxDate(now()->addMinutes(5)),
                ])
                ->action(function (array $data, Shipment $record) {
                    InventoryActions::run(fn () => app(ShipmentService::class)->updateStatus(
                        $record, $data['status'], $data['description'] ?? null, $data['location'] ?? null,
                        isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : null,
                    ));
                    Notification::make()->success()->title('Estado actualizado')->send();
                    $this->refreshFormData([]);
                }),
            Action::make('tracking')->label('Seguimiento')->icon(Heroicon::OutlinedHashtag)->color('gray')
                ->visible(fn (Shipment $record) => ! $record->isFinal() && $record->order->shippingLine?->type !== 'pickup')
                ->authorize($can)
                ->modalHeading('Número de seguimiento')
                ->fillForm(fn (Shipment $record) => $record->only(['carrier_code', 'tracking_number', 'tracking_url']))
                ->schema([
                    Select::make('carrier_code')->label('Courier')->placeholder('Despacho propio')
                        ->options(fn () => Courier::query()->where('enabled', true)->orderBy('name')->pluck('name', 'code')),
                    TextInput::make('tracking_number')->label('N.º de seguimiento')->maxLength(80),
                    TextInput::make('tracking_url')->label('Enlace de seguimiento')->url()->maxLength(500)
                        ->helperText('Opcional. Si el courier tiene configurada su página de seguimiento, el enlace se arma solo.'),
                ])
                ->action(function (array $data, Shipment $record) {
                    InventoryActions::run(fn () => app(ShipmentService::class)->setTracking($record, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null, $data['carrier_code'] ?? ''));
                    Notification::make()->success()->title('Seguimiento guardado')->send();
                    $this->refreshFormData([]);
                }),
            Action::make('incident')->label('Registrar incidencia')->icon(Heroicon::OutlinedExclamationTriangle)->color('danger')
                ->authorize($can)
                ->modalHeading('Registrar una incidencia')
                ->modalDescription('Queda en el despacho y en Logística → Incidencias. El cliente no la ve.')
                ->schema([
                    Select::make('type')->label('Tipo')->options(ShippingIncident::TYPES)->required(),
                    Textarea::make('description')->label('Qué pasó')->required()->maxLength(2000),
                    Select::make('owner_user_id')->label('Responsable')->placeholder('Sin asignar')
                        ->options(fn () => User::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data, Shipment $record) {
                    app(ShipmentService::class)->openIncident($record, $data['type'], $data['description'], $data['owner_user_id'] ?? null);
                    Notification::make()->success()->title('Incidencia registrada')->send();
                    $this->refreshFormData([]);
                }),
        ];
    }
}
