<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
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
                ->visible(fn (Order $record) => in_array($record->payment_status, ['pending', 'expired'], true))
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
