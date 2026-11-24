<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Domain\Notifications\Mail\TemplatePreviewMail;
use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;

class EditEmailTemplate extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = EmailTemplateResource::class;

    public function getTitle(): string
    {
        return 'Correo: '.$this->record->definition()['name'];
    }

    protected function helpManual(): string
    {
        return 'correos';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['version'] = $this->record->version + 1;
        $data['updated_by'] = auth()->id();
        if ($this->record->definition()['required']) {
            $data['enabled'] = true;
        }

        return $data;
    }

    /** Lo que está escrito ahora en el formulario, aunque no se haya guardado. */
    private function current(): array
    {
        $state = $this->form->getRawState();

        return [$state['subject'] ?? $this->record->subject, $state['heading'] ?? $this->record->heading, $state['intro'] ?? $this->record->intro];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('preview')->label('Vista previa')->icon(Heroicon::OutlinedEye)->color('gray')
                ->modalHeading('Vista previa con datos de ejemplo')
                ->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')->modalWidth('3xl')
                ->modalContent(function () {
                    [$subject, $heading, $intro] = $this->current();
                    $tpl = TemplatePreviewMail::texts($this->record->key, $subject, $heading, $intro);
                    $html = (string) app(Markdown::class)->render('mail.system.preview', [
                        'tpl' => $tpl, 'description' => $this->record->definition()['description'],
                        'store' => app(SettingsRepository::class)->get('business', 'store_name', config('app.name')),
                    ]);

                    return view('filament.emails.preview', ['subject' => $tpl['subject'], 'html' => $html]);
                }),
            Action::make('test')->label('Enviarme una prueba')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Enviar una prueba')
                ->modalDescription(fn () => 'Se envía a '.auth()->user()->email.' con datos de ejemplo y el texto que está escrito ahora (aunque no lo hayas guardado).')
                ->modalSubmitActionLabel('Enviar')
                ->action(function () {
                    [$subject, $heading, $intro] = $this->current();
                    try {
                        Mail::to(auth()->user()->email)->send(new TemplatePreviewMail($this->record->key, $subject, $heading, $intro));
                    } catch (\Throwable $e) {
                        Notification::make()->danger()->title('No se pudo enviar')->body('Revisa la configuración de correo (MAIL_* en el archivo .env). Detalle: '.mb_substr($e->getMessage(), 0, 200))->send();

                        return;
                    }
                    Notification::make()->success()->title('Prueba enviada a '.auth()->user()->email)->send();
                }),
            Action::make('restore')->label('Volver al texto original')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Volver al texto original')
                ->modalDescription('Se reemplazan el asunto, el título y el texto por los que trae el sistema.')
                ->modalSubmitActionLabel('Restaurar')
                ->action(function (EmailTemplate $record) {
                    $record->restoreDefault();
                    $this->fillForm();
                    Notification::make()->success()->title('Texto original restaurado')->send();
                }),
        ];
    }
}
