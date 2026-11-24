<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\PageService;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\FormErrors;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Editor de una página. Guardar crea una versión nueva (si algo cambió); publicar pone en la tienda
 * la última versión guardada. Vista previa abre la tienda con lo guardado, sin publicarlo.
 *
 * @property Page $record
 */
class EditPage extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = PageResource::class;

    public function getTitle(): string
    {
        return $this->record->title;
    }

    public function getSubheading(): ?string
    {
        $page = $this->record->fresh(['latestVersion', 'publishedVersion']);
        $latest = $page->latestVersion?->number;

        return match (true) {
            $page->status !== 'published' => "Borrador · versión $latest · no se ve en la tienda",
            $page->hasUnpublishedChanges() => "Publicada la versión {$page->publishedVersion?->number} · hay cambios guardados sin publicar (versión $latest)",
            default => "Publicada · versión $latest · ".$page->storefrontPath(),
        };
    }

    protected function helpManual(): string
    {
        return 'paginas';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['blocks'] = $this->record->latestVersion?->blocks ?? [];

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $pages = app(PageService::class);
        $blocks = $data['blocks'] ?? [];
        unset($data['blocks']);

        return FormErrors::run(function () use ($pages, $record, $data, $blocks) {
            $pages->update($record, $data);
            $pages->saveVersion($record, $blocks);

            return $record->refresh();
        });
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return $this->record->status === 'published' ? 'Guardado. Publica para que se vea en la tienda.' : 'Guardado';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('preview')->label('Vista previa')->icon(Heroicon::OutlinedEye)->color('gray')
                ->tooltip('Guarda los cambios y abre la página en la tienda sin publicarla')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $url = app(PageService::class)->previewUrl($this->record->refresh());
                    $this->js('window.open('.json_encode($url).', "_blank", "noopener")');
                }),
            Action::make('publish')->label('Publicar')->icon(Heroicon::OutlinedRocketLaunch)->color('success')
                ->authorize(fn () => auth()->user()?->can('content.publish'))
                ->requiresConfirmation()
                ->modalHeading('Publicar la página')
                ->modalDescription(fn () => 'Se guardan los cambios y la última versión queda visible en la tienda en '.$this->record->storefrontPath().'.')
                ->modalSubmitActionLabel('Publicar')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    FormErrors::run(fn () => app(PageService::class)->publish($this->record->refresh()), title: 'No se pudo publicar');
                    Notification::make()->success()->title('Página publicada')->body('La tienda se actualiza en unos segundos.')->send();
                    $this->refreshFormData(['status']);
                }),
            ActionGroup::make([
                Action::make('open')->label('Ver en la tienda')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->visible(fn () => $this->record->status === 'published')
                    ->url(fn () => rtrim((string) config('flux.storefront_url'), '/').$this->record->storefrontPath(), shouldOpenInNewTab: true),
                Action::make('unpublish')->label('Despublicar')->icon(Heroicon::OutlinedEyeSlash)->color('warning')
                    ->visible(fn () => $this->record->status === 'published')
                    ->authorize(fn () => auth()->user()?->can('content.publish'))
                    ->requiresConfirmation()
                    ->modalHeading('Despublicar la página')
                    ->modalDescription(fn () => $this->record->type === 'home'
                        ? 'La tienda vuelve a mostrar la portada fija. Los bloques y versiones se conservan.'
                        : 'La página deja de verse en la tienda (los enlaces a ella darán "no encontrada"). Los bloques y versiones se conservan.')
                    ->modalSubmitActionLabel('Despublicar')
                    ->action(function () {
                        app(PageService::class)->unpublish($this->record);
                        Notification::make()->success()->title('Página despublicada')->send();
                    }),
                DeleteAction::make()->label('Eliminar')
                    ->visible(fn () => $this->record->status !== 'published')
                    ->modalHeading('Eliminar la página')
                    ->modalDescription('Se borran la página y todas sus versiones. No se puede deshacer.'),
            ])->label('Más')->icon(Heroicon::OutlinedEllipsisVertical)->color('gray')->button(),
        ];
    }
}
