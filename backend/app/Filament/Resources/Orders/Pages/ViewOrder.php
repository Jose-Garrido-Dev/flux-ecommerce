<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Payments\Services\GatewayException;
use App\Domain\Payments\Services\PaymentProcessor;
use App\Domain\Payments\Services\RefundService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
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
