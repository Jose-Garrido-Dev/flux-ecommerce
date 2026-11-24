<?php

namespace App\Filament\Resources\Returns\Pages;

use App\Domain\Orders\Models\Payment;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Returns\Services\ReturnSettings;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Returns\ReturnResource;
use App\Filament\Support\InventoryActions;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ViewReturn extends ViewRecord
{
    use HasHelpManual;

    protected static string $resource = ReturnResource::class;

    public function getTitle(): string
    {
        return 'Devolución '.$this->record->label();
    }

    protected function helpManual(): string
    {
        return 'devoluciones';
    }

    private function service(): ReturnService
    {
        return app(ReturnService::class);
    }

    private function done(string $title): void
    {
        Notification::make()->success()->title($title)->send();
        $this->record->refresh();
        $this->refreshFormData([]);
    }

    protected function getHeaderActions(): array
    {
        $can = fn (string $permission) => fn () => auth()->user()?->can($permission);

        return [
            $this->helpManualAction(),
            Action::make('review')->label('Tomar para revisar')->icon(Heroicon::OutlinedEye)->color('gray')
                ->visible(fn (ReturnRequest $record) => $record->status === 'requested')
                ->authorize($can('returns.update'))
                ->modalHeading('Tomar la solicitud')
                ->modalSubmitActionLabel('Guardar')
                ->fillForm(['owner_user_id' => auth()->id()])
                ->schema([
                    Select::make('owner_user_id')->label('Responsable')->required()
                        ->options(fn () => User::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')),
                ])
                ->action(function (array $data, ReturnRequest $record) {
                    InventoryActions::run(fn () => $this->service()->startReview($record, (int) $data['owner_user_id']));
                    $this->done('Solicitud en revisión');
                }),
            Action::make('approve')->label('Aprobar')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                ->visible(fn (ReturnRequest $record) => $record->canMoveTo('approved'))
                ->authorize($can('returns.approve'))
                ->modalHeading('Aprobar la devolución')
                ->modalDescription('El cliente recibe un correo con estas instrucciones para enviar el producto.')
                ->modalSubmitActionLabel('Aprobar')
                ->fillForm(fn (ReturnRequest $record) => ['instructions' => $record->instructions ?: app(ReturnSettings::class)->instructions()])
                ->schema([
                    Textarea::make('instructions')->label('Instrucciones para el cliente')->required()->rows(5)->maxLength(2000)
                        ->helperText('Dirección, horario, quién paga el envío, cómo embalarlo.'),
                ])
                ->action(function (array $data, ReturnRequest $record) {
                    InventoryActions::run(fn () => $this->service()->approve($record, $data['instructions']));
                    $this->done('Devolución aprobada: se avisó al cliente');
                }),
            Action::make('reject')->label('Rechazar')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn (ReturnRequest $record) => $record->canMoveTo('rejected'))
                ->authorize($can('returns.approve'))
                ->modalHeading('Rechazar la devolución')
                ->modalDescription('El cliente recibe el motivo por correo.')
                ->modalSubmitActionLabel('Rechazar')
                ->schema([Textarea::make('rejection_reason')->label('Motivo')->required()->rows(3)->maxLength(1000)])
                ->action(function (array $data, ReturnRequest $record) {
                    InventoryActions::run(fn () => $this->service()->reject($record, $data['rejection_reason']));
                    $this->done('Devolución rechazada: se avisó al cliente');
                }),
            Action::make('receive')->label('Registrar recepción')->icon(Heroicon::OutlinedInboxArrowDown)->color('primary')
                ->visible(fn (ReturnRequest $record) => $record->status === 'approved')
                ->authorize($can('returns.update'))
                ->modalHeading('Recepción de los productos')
                ->modalDescription('Revisa cada producto. Lo que marques como "vuelve al stock" entra a la bodega principal como movimiento de devolución.')
                ->modalSubmitActionLabel('Registrar recepción')
                ->fillForm(fn (ReturnRequest $record) => ['conditions' => $record->items->mapWithKeys(fn ($i) => ['i'.$i->id => ['condition' => 'new', 'restock' => true]])->all()])
                ->schema(fn (ReturnRequest $record) => $record->items->map(fn ($item) => Grid::make(2)->schema([
                    Select::make("conditions.i{$item->id}.condition")
                        ->label($item->orderItem->product_name.($item->orderItem->variant_label ? ' · '.$item->orderItem->variant_label : '').' × '.$item->quantity)
                        ->options(ReturnRequest::CONDITIONS)->required()->live(),
                    Toggle::make("conditions.i{$item->id}.restock")->label('Vuelve al stock')->inline(false)
                        ->disabled(fn ($get) => $get("conditions.i{$item->id}.condition") === 'damaged'),
                ]))->all())
                ->action(function (array $data, ReturnRequest $record) {
                    $conditions = collect($data['conditions'] ?? [])->mapWithKeys(fn ($v, $k) => [(int) substr($k, 1) => [
                        'condition' => $v['condition'] ?? null, 'restock' => ($v['condition'] ?? null) !== 'damaged' && (bool) ($v['restock'] ?? false),
                    ]])->all();
                    InventoryActions::run(fn () => $this->service()->receive($record, $conditions));
                    $this->done('Recepción registrada');
                }),
            Action::make('process')->label('Resolver')->icon(Heroicon::OutlinedReceiptRefund)->color('success')
                ->visible(fn (ReturnRequest $record) => $record->status === 'received')
                ->authorize(fn (ReturnRequest $record) => auth()->user()?->can($record->type === 'return' ? 'refunds.process' : 'returns.update'))
                ->modalHeading(fn (ReturnRequest $record) => $record->type === 'return' ? 'Reembolsar la devolución' : 'Registrar el cambio')
                ->modalDescription(fn (ReturnRequest $record) => $record->type === 'return'
                    ? 'El reembolso se hace por el mismo medio de pago (Webpay devuelve a la tarjeta; una transferencia la devuelves tú desde el banco). El cliente recibe un correo.'
                    : 'Describe qué producto se envía en cambio. El cliente recibe un correo. Si hay que despacharlo, crea un pedido nuevo o un despacho.')
                ->modalSubmitActionLabel('Resolver')
                ->fillForm(fn (ReturnRequest $record) => ['refund_amount' => min($record->itemsTotal(), $this->refundable($record))])
                ->schema(fn (ReturnRequest $record) => [
                    TextInput::make('refund_amount')->label('Monto a reembolsar')->prefix('$')->integer()->minValue(0)->required()
                        ->maxValue($this->refundable($record))
                        ->helperText('Sugerido: lo pagado por esas unidades. Disponible para reembolsar en el pedido: '.OrderResource::money($this->refundable($record)).'. Pon 0 si no corresponde reembolso.')
                        ->visible($record->type === 'return'),
                    Textarea::make('resolution')->label($record->type === 'return' ? 'Comentario para el cliente (opcional)' : 'Qué se envía en cambio')
                        ->required($record->type === 'exchange')->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data, ReturnRequest $record) {
                    InventoryActions::run(fn () => $this->service()->process($record, isset($data['refund_amount']) ? (int) $data['refund_amount'] : null, $data['resolution'] ?? null));
                    $this->done('Devolución resuelta: se avisó al cliente');
                }),
            ActionGroup::make([
                Action::make('evidence')->label('Subir respaldo')->icon(Heroicon::OutlinedPaperClip)
                    ->authorize($can('returns.update'))
                    ->modalHeading('Subir fotos o documentos')
                    ->modalSubmitActionLabel('Subir')
                    ->schema([
                        FileUpload::make('files')->label('Archivos')->multiple()->maxFiles(10)->required()
                            ->disk('local')->directory(fn (ReturnRequest $record) => 'devoluciones/'.$record->uuid)->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])->maxSize(8192)
                            ->storeFileNamesIn('names')
                            ->helperText('JPG, PNG, WEBP o PDF, hasta 8 MB cada uno. Quedan en el servidor, no son públicos.'),
                    ])
                    ->action(function (array $data, ReturnRequest $record) {
                        foreach ((array) $data['files'] as $path) {
                            $record->evidence()->create(['path' => $path, 'original_name' => $data['names'][$path] ?? basename($path), 'uploaded_by' => auth()->id()]);
                        }
                        $this->done('Respaldo guardado');
                    }),
                Action::make('close')->label('Cerrar')->icon(Heroicon::OutlinedArchiveBox)
                    ->visible(fn (ReturnRequest $record) => $record->canMoveTo('closed'))
                    ->authorize($can('returns.update'))
                    ->requiresConfirmation()->modalHeading('Cerrar la devolución')->modalDescription('Queda archivada. No se avisa al cliente.')
                    ->modalSubmitActionLabel('Cerrar')
                    ->action(function (ReturnRequest $record) {
                        InventoryActions::run(fn () => $this->service()->close($record));
                        $this->done('Devolución cerrada');
                    }),
                Action::make('cancel')->label('Anular')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (ReturnRequest $record) => $record->canMoveTo('cancelled'))
                    ->authorize($can('returns.update'))
                    ->requiresConfirmation()->modalHeading('Anular la devolución')
                    ->modalDescription('Úsalo si el cliente desiste. Las unidades vuelven a quedar disponibles para otra solicitud.')
                    ->modalSubmitActionLabel('Anular')->modalCancelActionLabel('Volver')
                    ->action(function (ReturnRequest $record) {
                        InventoryActions::run(fn () => $this->service()->cancel($record, 'user'));
                        $this->done('Devolución anulada');
                    }),
            ])->label('Más acciones')->icon(Heroicon::OutlinedEllipsisVertical)->button()->color('gray'),
        ];
    }

    private function refundable(ReturnRequest $record): int
    {
        return (int) (Payment::query()->where('order_id', $record->order_id)->where('status', 'paid')->latest('id')->first()?->refundable() ?? 0);
    }
}
