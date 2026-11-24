<?php

namespace App\Filament\Resources\MediaLibrary\Pages;

use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Services\MediaUploader;
use App\Domain\Content\Models\MediaFolder;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\MediaFolders\MediaFolderResource;
use App\Filament\Resources\MediaLibrary\MediaResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageMedia extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = MediaResource::class;

    protected function helpManual(): string
    {
        return 'biblioteca';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('folders')->label('Carpetas')->icon(Heroicon::OutlinedFolder)->color('gray')
                ->url(fn () => MediaFolderResource::getUrl()),
            Action::make('upload')->label('Subir imágenes')->icon(Heroicon::OutlinedArrowUpTray)
                ->authorize(fn () => auth()->user()?->can('media.create'))
                ->modalHeading('Subir imágenes')->modalSubmitActionLabel('Subir')
                ->schema([
                    FileUpload::make('files')->label('Imágenes')->multiple()->image()->required()
                        ->disk('local')->directory('tmp-uploads')->storeFiles(false)
                        ->acceptedFileTypes(array_keys(MediaUploader::ALLOWED))
                        ->maxSize(MediaUploader::MAX_BYTES / 1024)->maxFiles(30)
                        ->helperText('JPG, PNG, WebP o GIF hasta 10 MB cada una. Se optimizan y se les quitan los datos de ubicación. Una imagen repetida no se duplica.'),
                    Select::make('folder_id')->label('Carpeta')->placeholder('Sin carpeta')
                        ->options(fn () => MediaFolder::query()->with('parent')->get()->mapWithKeys(fn (MediaFolder $f) => [$f->id => $f->path()])->sort()->all()),
                ])
                ->action(function (array $data) {
                    $uploader = app(MediaUploader::class);
                    $count = 0;
                    foreach ((array) ($data['files'] ?? []) as $file) {
                        if (! $file instanceof TemporaryUploadedFile) {
                            continue;
                        }
                        /** @var Media $media */
                        $media = InventoryActions::run(fn () => $uploader->store($file->getRealPath(), $file->getClientOriginalName(), null, 'contenido'));
                        if (filled($data['folder_id'] ?? null) && $media->folder_id === null) {
                            $media->update(['folder_id' => (int) $data['folder_id']]);
                        }
                        $count++;
                    }
                    Notification::make()->success()->title($count === 1 ? '1 imagen subida' : "$count imágenes subidas")
                        ->body('Agrega el texto alternativo a cada una con "Editar".')->send();
                }),
        ];
    }
}
