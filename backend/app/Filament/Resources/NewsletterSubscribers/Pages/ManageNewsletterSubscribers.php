<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Services\NewsletterService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManageNewsletterSubscribers extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = NewsletterSubscriberResource::class;

    protected function helpManual(): string
    {
        return 'boletin';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('export')->label('Exportar suscritos (CSV)')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->authorize(fn () => auth()->user()->can('marketing.view'))
                ->action(fn () => $this->export()),
            Action::make('add')->label('Agregar suscriptor')->icon(Heroicon::OutlinedPlus)
                ->authorize(fn () => auth()->user()->can('marketing.create'))
                ->modalHeading('Agregar suscriptor')
                ->modalDescription('Le enviamos un correo para que confirme. Hasta que lo haga queda "Por confirmar" y no recibe campañas.')
                ->modalSubmitActionLabel('Enviar confirmación')
                ->schema([
                    TextInput::make('email')->label('Correo')->email()->required()->maxLength(190),
                    TextInput::make('name')->label('Nombre')->maxLength(120),
                ])
                ->action(function (array $data) {
                    $s = app(NewsletterService::class)->subscribe($data['email'], $data['name'] ?? null, 'admin');
                    Notification::make()->success()->title($s->status === 'subscribed' ? 'Ese correo ya está suscrito' : 'Correo de confirmación en cola')->send();
                }),
        ];
    }

    /** Solo suscritos confirmados: es la lista que sirve para enviar. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['correo', 'nombre', 'origen', 'confirmado'], ';');
            NewsletterSubscriber::query()->subscribed()->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, $s->name, NewsletterSubscriber::SOURCES[$s->source] ?? $s->source, $s->confirmed_at?->format('d-m-Y H:i')], ';');
                }
            });
            fclose($out);
        }, 'suscritos-boletin-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
