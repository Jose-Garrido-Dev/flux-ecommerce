<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\PaymentProcessor;
use App\Domain\Payments\Services\RefundService;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Services\ShipmentService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    use HasHelpManual;

    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Pedido N.º '.$this->record->number;
    }

    protected function helpManual(): string
    {
        return 'pedidos';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('confirmPayment')->label('Confirmar pago')->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn (Order $record) => in_array($record->payment_status, ['pending', 'expired'], true)
                    && $record->latestPayment?->method_code === 'bank_transfer')
                ->authorize(fn () => auth()->user()?->can('payments.confirm'))
                ->modalHeading('Confirmar la transferencia')
                ->modalDescription(fn (Order $record) => 'Revisa en tu banco que llegó la transferencia por '.OrderResource::money($record->grand_total)
                    .'. Al confirmar se descuenta el stock y se avisa al cliente por correo.'
                    .($record->payment_status === 'expired' ? ' El plazo venció: se intentará reservar el stock de nuevo.' : ''))
                ->modalSubmitActionLabel('Confirmar pago')
                ->fillForm(fn (Order $record) => ['amount' => $record->grand_total])
                ->schema([
                    TextInput::make('amount')->label('Monto recibido')->prefix('$')->integer()->minValue(1)->required(),
                    TextInput::make('reference')->label('N.º de operación')->maxLength(120)->helperText('Opcional. Queda en el historial del pedido.'),
                ])
                ->action(function (array $data, Order $record) {
                    InventoryActions::run(fn () => app(OrderPayments::class)->confirm($record, (int) $data['amount'], $data['reference'] ?? null));
                    Notification::make()->success()->title('Pago confirmado')->body('Se descontó el stock y se avisó al cliente.')->send();
                    $this->refreshFormData([]);
                }),
            Action::make('checkGateway')->label('Consultar en Webpay')->icon(Heroicon::OutlinedArrowPath)
                ->visible(fn (Order $record) => ($p = $record->latestPayment) && $p->status === 'pending' && $p->external_id && $p->method_code !== 'bank_transfer')
                ->authorize(fn () => auth()->user()?->can('payments.reconcile'))
                ->requiresConfirmation()
                ->modalHeading('Consultar el pago en la pasarela')
                ->modalDescription('Pregunta a la pasarela si el cliente pagó. Si el pago está aprobado y el monto coincide, el pedido queda pagado.')
                ->modalSubmitActionLabel('Consultar')
                ->action(function (Order $record) {
                    try {
                        $outcome = app(PaymentProcessor::class)->resolve($record->latestPayment);
                    } catch (GatewayException $e) {
                        Notification::make()->danger()->title('No se pudo consultar')->body($e->getMessage())->send();

                        return;
                    }
                    [$title, $type] = match ($outcome) {
                        'paid' => ['El pago está aprobado: el pedido quedó pagado', 'success'],
                        'rejected' => ['La pasarela informa que el pago fue rechazado', 'warning'],
                        'aborted' => ['El cliente no completó el pago', 'warning'],
                        default => ['Todavía no hay resultado. Vuelve a consultar en unos minutos', 'info'],
                    };
                    Notification::make()->{$type}()->title($title)->send();
                    $this->refreshFormData([]);
                }),
            Action::make('ship')->label('Crear despacho')->icon(Heroicon::OutlinedPaperAirplane)->color('primary')
                ->visible(fn (Order $record) => $record->status === 'processing'
                    && in_array($record->payment_status, ['paid', 'partially_refunded'], true)
                    && array_sum(app(ShipmentService::class)->remaining($record)) > 0)
                ->authorize(fn () => auth()->user()?->can('shipping.dispatch'))
                ->modalHeading(fn (Order $record) => $record->shippingLine?->type === 'pickup' ? 'Preparar el retiro en tienda' : 'Crear despacho')
                ->modalDescription('Indica cuánto va en este despacho. Puedes despachar en partes: lo que quede se envía en otro despacho.')
                ->modalSubmitActionLabel('Crear despacho')
                ->fillForm(function (Order $record) {
                    $left = app(ShipmentService::class)->remaining($record);

                    return [
                        'items' => collect($left)->mapWithKeys(fn ($qty, $id) => ['i'.$id => $qty])->all(),
                        'carrier_code' => $record->shippingLine?->carrier_code,
                        'packages' => 1,
                    ];
                })
                ->schema(fn (Order $record) => [
                    Section::make('Productos')->schema(collect(app(ShipmentService::class)->remaining($record))
                        ->filter(fn ($qty) => $qty > 0)
                        ->map(function ($qty, $id) use ($record) {
                            $item = $record->items->firstWhere('id', $id);

                            return TextInput::make('items.i'.$id)->label($item->product_name.($item->variant_label ? ' · '.$item->variant_label : ''))
                                ->helperText('SKU '.$item->sku.' · faltan '.$qty)->integer()->minValue(0)->maxValue($qty)->required();
                        })->values()->all()),
                    Grid::make(2)->visible($record->shippingLine?->type !== 'pickup')->schema([
                        Select::make('carrier_code')->label('Courier')->placeholder('Despacho propio')
                            ->options(fn () => Courier::query()->where('enabled', true)->orderBy('name')->pluck('name', 'code')),
                        TextInput::make('tracking_number')->label('N.º de seguimiento')->maxLength(80)->helperText('Puedes agregarlo después.'),
                        TextInput::make('packages')->label('Bultos')->integer()->minValue(1)->maxValue(99),
                        TextInput::make('weight_kg')->label('Peso')->suffix('kg')->numeric()->minValue(0),
                        TextInput::make('cost_amount')->label('Costo del courier')->prefix('$')->integer()->minValue(0)
                            ->helperText('Lo que te cobra el courier. Solo uso interno.'),
                    ]),
                    Textarea::make('notes')->label('Notas internas')->maxLength(1000),
                ])
                ->action(function (array $data, Order $record) {
                    $items = collect($data['items'] ?? [])->mapWithKeys(fn ($qty, $key) => [(int) substr($key, 1) => (int) $qty])
                        ->filter(fn ($qty) => $qty > 0)->all();
                    if ($items === []) {
                        Notification::make()->danger()->title('Indica al menos un producto')->send();

                        return;
                    }
                    $shipment = InventoryActions::run(fn () => app(ShipmentService::class)->create($record, $items, [
                        'carrier_code' => $data['carrier_code'] ?? null,
                        'tracking_number' => $data['tracking_number'] ?? null,
                        'packages' => $data['packages'] ?? 1,
                        'weight_g' => filled($data['weight_kg'] ?? null) ? (int) round((float) $data['weight_kg'] * 1000) : null,
                        'cost_amount' => filled($data['cost_amount'] ?? null) ? (int) $data['cost_amount'] : null,
                        'notes' => $data['notes'] ?? null,
                    ]));
                    Notification::make()->success()->title('Despacho creado')
                        ->body('Cuando salga, márcalo como Despachado en el despacho: el cliente recibe un correo con el seguimiento.')->send();
                    $this->redirect(ShipmentResource::getUrl('view', ['record' => $shipment]));
                }),
            Action::make('refund')->label('Reembolsar')->icon(Heroicon::OutlinedReceiptRefund)->color('warning')
                ->visible(fn (Order $record) => ($record->payments()->where('status', 'paid')->latest('id')->first()?->refundable() ?? 0) > 0)
                ->authorize(fn () => auth()->user()?->can('refunds.process'))
                ->modalHeading('Reembolsar')
                ->modalDescription(function (Order $record) {
                    $payment = $record->payments()->where('status', 'paid')->latest('id')->first();

                    return $payment?->method_code === 'bank_transfer'
                        ? 'El pedido se pagó por transferencia: devuelve el dinero desde tu banco y registra aquí el reembolso. El cliente recibe un correo.'
                        : 'El reembolso se pide a '.$payment?->methodLabel().' y vuelve a la tarjeta del cliente. Un reembolso total de un pedido sin despachar lo anula.';
                })
                ->modalSubmitActionLabel('Reembolsar')->modalCancelActionLabel('Volver')
                ->fillForm(fn (Order $record) => [
                    'amount' => $record->payments()->where('status', 'paid')->latest('id')->first()?->refundable(),
                    'restock' => $record->fulfillment_status === 'unfulfilled',
                ])
                ->schema([
                    TextInput::make('amount')->label('Monto a reembolsar')->prefix('$')->integer()->minValue(1)->required()
                        ->helperText(fn (Order $record) => 'Disponible para reembolsar: '.OrderResource::money($record->payments()->where('status', 'paid')->latest('id')->first()?->refundable())),
                    TextInput::make('reason')->label('Motivo')->required()->maxLength(300),
                    Toggle::make('restock')->label('Devolver los productos al stock')
                        ->helperText('Solo con el reembolso total. Para devoluciones parciales, registra la entrada en Inventario.'),
                ])
                ->action(function (array $data, Order $record) {
                    InventoryActions::run(fn () => app(RefundService::class)->refund($record, (int) $data['amount'], $data['reason'], (bool) ($data['restock'] ?? false)));
                    Notification::make()->success()->title('Reembolso hecho')->body('Se avisó al cliente por correo.')->send();
                    $this->refreshFormData([]);
                }),
            Action::make('cancel')->label('Cancelar pedido')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn (Order $record) => $record->isCancellable())
                ->authorize(fn () => auth()->user()?->can('orders.cancel'))
                ->modalHeading('Cancelar pedido')
                ->modalDescription('Se libera el stock reservado. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Cancelar pedido')->modalCancelActionLabel('Volver')
                ->schema([TextInput::make('reason')->label('Motivo')->required()->maxLength(255)])
                ->action(function (array $data, Order $record) {
                    InventoryActions::run(fn () => app(OrderPayments::class)->cancel($record, $data['reason']));
                    Notification::make()->success()->title('Pedido cancelado')->send();
                }),
        ];
    }
}
