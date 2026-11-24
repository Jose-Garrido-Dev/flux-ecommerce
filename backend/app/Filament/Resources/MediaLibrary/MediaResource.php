<?php

namespace App\Filament\Resources\MediaLibrary;

use App\Domain\Catalog\Models\Media;
use App\Domain\Content\Models\MediaFolder;
use App\Domain\Content\Services\MediaUsage;
use App\Filament\Resources\MediaLibrary\Pages\ManageMedia;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/** Contenido → Biblioteca: todas las imágenes de la tienda, con carpetas, texto alternativo y control de uso. */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $slug = 'media';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Biblioteca';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'imagen';

    protected static ?string $pluralModelLabel = 'Biblioteca multimedia';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alt')->label('Texto alternativo')->maxLength(250)->columnSpanFull()
                ->helperText('Describe lo que muestra la imagen. Lo leen los lectores de pantalla y los buscadores.'),
            TextInput::make('title')->label('Nombre interno')->maxLength(250)->helperText('Para encontrarla en la biblioteca.'),
            Select::make('folder_id')->label('Carpeta')->options(fn () => MediaFolder::query()->with('parent')->get()->mapWithKeys(fn (MediaFolder $f) => [$f->id => $f->path()])->sort()->all())
                ->placeholder('Sin carpeta'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('folder.parent'))
            ->defaultSort('id', 'desc')
            ->contentGrid(['sm' => 2, 'md' => 3, 'xl' => 5])
            ->columns([
                Stack::make([
                    ImageColumn::make('thumb')->label('Imagen')->state(fn (Media $record) => $record->thumbUrl())
                        ->imageHeight(140)->extraImgAttributes(['class' => 'w-full object-cover rounded-lg', 'loading' => 'lazy', 'alt' => '']),
                    TextColumn::make('title')->label('Nombre')->weight('medium')->limit(40)
                        ->state(fn (Media $record) => $record->title ?: $record->original_name ?: 'Imagen '.$record->id)
                        ->searchable(['title', 'alt', 'original_name']),
                    TextColumn::make('alt')->label('Texto alternativo')->color(fn (?string $state) => $state ? 'gray' : 'warning')
                        ->placeholder('Sin texto alternativo')->limit(60)->size('sm'),
                    TextColumn::make('meta')->label('Detalle')->size('xs')->color('gray')
                        ->state(fn (Media $record) => trim(($record->width ? "{$record->width} × {$record->height} px · " : '').number_format($record->size / 1024, 0, ',', '.').' KB'.($record->folder ? ' · '.$record->folder->path() : ''))),
                ])->space(1),
            ])
            ->filters([
                SelectFilter::make('folder_id')->label('Carpeta')->options(fn () => MediaFolder::query()->with('parent')->get()->mapWithKeys(fn (MediaFolder $f) => [$f->id => $f->path()])->sort()->all()),
                TernaryFilter::make('alt')->label('Texto alternativo')->trueLabel('Con texto')->falseLabel('Sin texto')
                    ->queries(true: fn (Builder $query) => $query->whereNotNull('alt')->where('alt', '!=', ''), false: fn (Builder $query) => $query->where(fn ($w) => $w->whereNull('alt')->orWhere('alt', ''))),
            ])
            ->recordActions([
                EditAction::make()->label('Editar')->modalHeading('Editar imagen'),
                Action::make('usage')->label('Dónde se usa')->icon(Heroicon::OutlinedLink)->color('gray')
                    ->modalHeading('Dónde se usa')->modalSubmitAction(false)->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (Media $record) => view('filament.media.usage', ['uses' => app(MediaUsage::class)->for($record), 'url' => $record->url()])),
                Action::make('delete')->label('Eliminar')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->authorize(fn () => auth()->user()?->can('media.delete'))
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar imagen')
                    ->modalDescription('Se borra el archivo del servidor. No se puede deshacer.')
                    ->modalSubmitActionLabel('Eliminar')
                    ->action(function (Media $record) {
                        $uses = app(MediaUsage::class)->for($record);
                        if ($uses !== []) {
                            Notification::make()->danger()->title('La imagen está en uso')->body('Quítala primero de: '.implode('; ', array_slice($uses, 0, 5)).(count($uses) > 5 ? '…' : '.'))->persistent()->send();

                            return;
                        }
                        $disk = Storage::disk($record->disk);
                        $disk->delete(array_filter([$record->path, ...array_values($record->variants ?? [])]));
                        $record->delete();
                        Notification::make()->success()->title('Imagen eliminada')->send();
                    }),
            ])
            ->paginated([20, 40, 80])
            ->emptyStateHeading('La biblioteca está vacía')
            ->emptyStateDescription('Sube imágenes con el botón "Subir imágenes". También aparecen aquí las fotos de los productos.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageMedia::route('/')];
    }
}
