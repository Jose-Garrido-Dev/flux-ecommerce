<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Services\CampaignService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Campaigns\CampaignResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** @property Campaign $record */
class EditCampaign extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = CampaignResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        $c = $this->record;

        return match ($c->status) {
            'draft' => 'Borrador: no se ha enviado.',
            'scheduled' => 'Programada para el '.$c->scheduled_at?->format('d-m-Y H:i').'. Puedes editarla hasta esa hora.',
            'sending' => "Enviando: {$c->sent_count} de {$c->recipients_count} enviados.",
            'sent' => "Enviada el {$c->finished_at?->format('d-m-Y H:i')}: {$c->sent_count} enviados, {$c->failed_count} fallidos, {$c->clicks_count} clics.",
            default => 'Cancelada.',
        };
    }

    protected function helpManual(): string
    {
        return 'campanas';
    }

    protected function getFormActions(): array
    {
        return $this->record->isEditable() ? parent::getFormActions() : [];
    }

    protected function getHeaderActions(): array
    {
        $service = app(CampaignService::class);
        $guard = function (callable $fn) {
            try {
                $fn();
            } catch (ValidationException $e) {
                Notification::make()->danger()->title('No se pudo completar')->body(collect($e->errors())->flatten()->implode("\n"))->send();
            }
        };

        return [
            $this->helpManualAction(),
            Action::make('test')->label('Enviar prueba')->icon(Heroicon::OutlinedBeaker)->color('gray')
                ->authorize(fn () => auth()->user()->can('marketing.update'))
                ->modalHeading('Enviar una prueba')
                ->modalDescription('Se envía con el contenido guardado. Guarda los cambios antes de probar.')
                ->modalSubmitActionLabel('Enviar')
                ->schema([TextInput::make('email')->label('Correo')->email()->required()->default(fn () => auth()->user()->email)])
                ->action(fn (array $data) => $guard(function () use ($service, $data) {
                    $service->sendTest($this->record, $data['email']);
                    Notification::make()->success()->title('Prueba enviada a '.$data['email'])->send();
                })),
            Action::make('schedule')->label('Programar envío')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn () => $this->record->isEditable())
                ->authorize(fn () => auth()->user()->can('marketing.send'))
                ->modalHeading('Programar el envío')
                ->modalDescription(fn () => 'Se envía a los suscritos confirmados de la audiencia elegida, en lotes de '.CampaignService::BATCH.' cada '.CampaignService::BATCH_DELAY.' segundos. Quien se dé de baja antes de su lote no la recibe.')
                ->modalSubmitActionLabel('Confirmar')
                ->schema([
                    Toggle::make('now')->label('Enviar ahora')->default(true)->live(),
                    DateTimePicker::make('at')->label('Fecha y hora de envío')->seconds(false)->native(false)->displayFormat('d-m-Y H:i')
                        ->hidden(fn (Get $get) => (bool) $get('now'))->required(fn (Get $get) => ! $get('now'))
                        ->rule('after:now')->validationMessages(['after' => 'La fecha debe ser futura.']),
                ])
                ->action(fn (array $data) => $guard(function () use ($service, $data) {
                    $service->schedule($this->record, $data['now'] ? null : Carbon::parse($data['at']));
                    $this->record->refresh();
                    Notification::make()->success()->title($data['now'] ? 'Envío iniciado' : 'Envío programado')->send();
                    $this->redirect(CampaignResource::getUrl('edit', ['record' => $this->record]));
                })),
            Action::make('cancel')->label('Cancelar envío')->icon(Heroicon::OutlinedXCircle)->color('danger')
                ->visible(fn () => in_array($this->record->status, ['scheduled', 'sending'], true))
                ->authorize(fn () => auth()->user()->can('marketing.send'))
                ->requiresConfirmation()
                ->modalDescription('Los correos que aún no salen no se enviarán. Los ya enviados no se pueden recuperar.')
                ->action(fn () => $guard(function () use ($service) {
                    $service->cancel($this->record);
                    Notification::make()->success()->title('Envío cancelado')->send();
                    $this->redirect(CampaignResource::getUrl('edit', ['record' => $this->record]));
                })),
            Action::make('retry')->label('Reintentar fallidos')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->visible(fn () => in_array($this->record->status, ['sent', 'sending'], true) && $this->record->failed_count > 0)
                ->authorize(fn () => auth()->user()->can('marketing.send'))
                ->requiresConfirmation()
                ->action(function () use ($service) {
                    $n = $service->retryFailed($this->record);
                    Notification::make()->success()->title("Reintentando $n correos")->send();
                }),
            DeleteAction::make()->visible(fn () => $this->record->status === 'draft'),
        ];
    }
}
