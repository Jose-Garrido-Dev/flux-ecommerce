<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\MediaUploader;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Pestaña "Imágenes" (módulo 2, pestaña D): subir varias a la vez, ordenar arrastrando,
 * elegir la principal, texto alternativo y asociación a una variante.
 * Cada imagen pasa por MediaUploader: valida el tipo real, corrige orientación y genera miniatura.
 */
class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'Imágenes';

    protected static ?string $modelLabel = 'imagen';

    protected static ?string $pluralModelLabel = 'imágenes';

    /** Sin permiso de edición de productos la pestaña queda solo de lectura. */
    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('products.update');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('alt')->label('Texto alternativo')->maxLength(255)
                ->helperText('Describe la imagen para lectores de pantalla y buscadores. Ej.: "Polera negra de algodón, vista frontal".')
                ->columnSpanFull(),
            TextInput::make('title')->label('Título')->maxLength(255),
            Select::make('variant_id')->label('Variante')
                ->options(fn () => $this->variantOptions())
                ->placeholder('Todas (imagen general)')
                ->helperText('Si eliges una variante, esta imagen se muestra al seleccionarla.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->columns([
                ImageColumn::make('thumb')->label('')->state(fn (Media $r) => $r->thumbUrl())->size(72),
                TextColumn::make('alt')->label('Texto alternativo')->placeholder('Falta: agrégalo para SEO y accesibilidad')->wrap(),
                TextColumn::make('size_label')->label('Tamaño')
                    ->state(fn (Media $r) => "{$r->width} × {$r->height} px · ".number_format($r->size / 1024, 0, ',', '.').' KB'),
                TextColumn::make('variant')->label('Variante')
                    ->state(fn (Media $r) => $this->variantOptions()[$r->pivot->variant_id] ?? null)->placeholder('General'),
                IconColumn::make('is_primary')->label('Principal')->state(fn (Media $r) => (bool) $r->pivot->is_primary)->boolean(),
            ])
            ->headerActions([
                Action::make('upload')->label('Subir imágenes')->icon(Heroicon::OutlinedArrowUpTray)
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->schema([
                        FileUpload::make('files')->label('Imágenes')->multiple()->image()->required()
                            ->disk('local')->directory('tmp-uploads')->storeFiles(false)
                            ->acceptedFileTypes(array_keys(MediaUploader::ALLOWED))
                            ->maxSize(MediaUploader::MAX_BYTES / 1024)->maxFiles(20)->reorderable()
                            ->helperText('JPG, PNG, WebP o GIF hasta 10 MB. Se optimizan automáticamente (máximo 2400 px).'),
                    ])
                    ->action(fn (array $data) => $this->upload($data['files'] ?? [])),
                Action::make('attachExisting')->label('Usar imagen ya subida')->icon(Heroicon::OutlinedPhoto)->color('gray')
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->schema([
                        Select::make('media_id')->label('Imagen')->required()->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Media::query()
                                ->where(fn ($q) => $q->where('alt', 'like', "%$search%")->orWhere('original_name', 'like', "%$search%"))
                                ->whereNotIn('id', $this->getOwnerRecord()->media()->select('media.id'))
                                ->limit(30)->get()->mapWithKeys(fn ($m) => [$m->id => $m->alt ?: $m->original_name ?: "Imagen {$m->id}"])),
                    ])
                    ->action(function (array $data) {
                        $this->attach([(int) $data['media_id']]);
                        Notification::make()->success()->title('Imagen agregada')->send();
                    }),
            ])
            ->recordActions([
                Action::make('makePrimary')->label('Principal')->icon(Heroicon::OutlinedStar)
                    ->visible(fn (Media $r) => ! $r->pivot->is_primary)
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->action(function (Media $record) {
                        $this->makePrimary($record->id);
                        Notification::make()->success()->title('Imagen principal actualizada')->send();
                    }),
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Media $record) => [...$data, 'variant_id' => $record->pivot->variant_id])
                    ->using(function (Media $record, array $data) {
                        $record->update(['alt' => $data['alt'] ?? null, 'title' => $data['title'] ?? null]);
                        $this->getOwnerRecord()->media()->updateExistingPivot($record->id, ['variant_id' => $data['variant_id'] ?? null]);

                        return $record;
                    }),
                DetachAction::make()->label('Quitar')
                    ->modalDescription('La imagen deja de mostrarse en este producto. El archivo se conserva en la biblioteca.')
                    ->after(fn () => $this->ensurePrimary()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Quitar seleccionadas')->after(fn () => $this->ensurePrimary()),
                ]),
            ])
            ->emptyStateHeading('Sin imágenes')
            ->emptyStateDescription('Sube al menos una imagen: los productos sin imagen venden menos y se marcan en el listado.');
    }

    /** @param  array<int, TemporaryUploadedFile|string>  $files */
    private function upload(array $files): void
    {
        $uploader = app(MediaUploader::class);
        $ids = [];
        $failed = [];
        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }
            try {
                $ids[] = $uploader->store($file->getRealPath(), $file->getClientOriginalName(), $this->getOwnerRecord()->name, 'catalog')->id;
            } catch (ValidationException $e) {
                $failed[] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();
            }
        }
        $this->attach($ids);

        Notification::make()->{$failed ? 'warning' : 'success'}()
            ->title(count($ids).' imágenes agregadas')
            ->body($failed ? 'No se pudieron subir: '.implode('; ', $failed) : null)
            ->persistent((bool) $failed)->send();
    }

    /** @param  list<int>  $mediaIds */
    private function attach(array $mediaIds): void
    {
        $product = $this->getOwnerRecord();
        $already = $product->media()->pluck('media.id')->all();
        $position = (int) DB::table('product_media')->where('product_id', $product->id)->max('position');
        foreach (array_diff(array_unique($mediaIds), $already) as $id) {
            $product->media()->attach($id, ['position' => ++$position, 'is_primary' => false]);
        }
        $this->ensurePrimary();
    }

    private function makePrimary(int $mediaId): void
    {
        DB::transaction(function () use ($mediaId) {
            $pivot = DB::table('product_media')->where('product_id', $this->getOwnerRecord()->id);
            (clone $pivot)->update(['is_primary' => false]);
            (clone $pivot)->where('media_id', $mediaId)->update(['is_primary' => true]);
        });
    }

    /** Siempre hay una imagen principal si el producto tiene imágenes. */
    private function ensurePrimary(): void
    {
        $pivot = DB::table('product_media')->where('product_id', $this->getOwnerRecord()->id);
        if (! (clone $pivot)->where('is_primary', true)->exists() && ($first = (clone $pivot)->orderBy('position')->value('media_id'))) {
            $this->makePrimary($first);
        }
    }

    /** @return array<int, string> */
    private function variantOptions(): array
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return once(fn () => $product->variants()->with('attributeValues')->get()
            ->mapWithKeys(fn ($v) => [$v->id => $v->attributeValues->pluck('label')->implode(' / ') ?: $v->sku])->all());
    }
}
