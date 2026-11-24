<?php

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageVersion;
use App\Domain\Content\Services\BlockSchema;
use App\Domain\Content\Services\PageService;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Historial de versiones: ver, restaurar (crea una versión nueva) o publicar una versión anterior. */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Versiones';

    protected static ?string $modelLabel = 'versión';

    protected static ?string $pluralModelLabel = 'versiones';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Page $page */
        $page = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('author'))
            ->defaultSort('number', 'desc')
            ->columns([
                TextColumn::make('number')->label('Versión')->prefix('v')
                    ->badge(fn (PageVersion $record) => $record->id === $page->published_version_id)
                    ->color(fn (PageVersion $record) => $record->id === $page->published_version_id ? 'success' : null)
                    ->description(fn (PageVersion $record) => $record->id === $page->published_version_id ? 'Publicada' : null),
                TextColumn::make('created_at')->label('Guardada')->dateTime('d-m-Y H:i'),
                TextColumn::make('author.name')->label('Por')->placeholder('Sistema'),
                TextColumn::make('blocks')->label('Bloques')
                    ->state(fn (PageVersion $record) => collect($record->blocks)->map(fn ($b) => BlockSchema::get($b['type'])['label'] ?? $b['type'])->implode(', ') ?: 'Sin bloques')
                    ->wrap()->limit(120),
                TextColumn::make('note')->label('Nota')->placeholder('—'),
            ])
            ->recordActions([
                Action::make('preview')->label('Vista previa')->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (PageVersion $record) => app(PageService::class)->previewUrl($page, $record), shouldOpenInNewTab: true),
                Action::make('restore')->label('Restaurar')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                    ->visible(fn (PageVersion $record) => $record->number !== $page->versions()->max('number'))
                    ->authorize(fn () => auth()->user()?->can('content.update'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (PageVersion $record) => "Restaurar la versión {$record->number}")
                    ->modalDescription('Se copia como la versión más nueva para seguir editándola. No se publica sola y no se pierde ninguna versión.')
                    ->modalSubmitActionLabel('Restaurar')
                    ->action(function (PageVersion $record) use ($page) {
                        $version = InventoryActions::run(fn () => app(PageService::class)->restore($page, $record));
                        Notification::make()->success()->title("Versión {$record->number} restaurada como versión {$version->number}")->body('Revísala y publícala cuando esté lista.')->send();
                        $this->redirect(PageResource::getUrl('edit', ['record' => $page]));
                    }),
                Action::make('publishVersion')->label('Publicar esta')->icon(Heroicon::OutlinedRocketLaunch)->color('success')
                    ->visible(fn (PageVersion $record) => $record->id !== $page->published_version_id)
                    ->authorize(fn () => auth()->user()?->can('content.publish'))
                    ->requiresConfirmation()
                    ->modalHeading(fn (PageVersion $record) => "Publicar la versión {$record->number}")
                    ->modalDescription('La tienda muestra esta versión. Lo que estés editando se mantiene guardado como borrador.')
                    ->modalSubmitActionLabel('Publicar')
                    ->action(function (PageVersion $record) use ($page) {
                        InventoryActions::run(fn () => app(PageService::class)->publish($page, $record));
                        Notification::make()->success()->title("Versión {$record->number} publicada")->send();
                    }),
            ])
            ->paginated([10, 25, 50]);
    }
}
