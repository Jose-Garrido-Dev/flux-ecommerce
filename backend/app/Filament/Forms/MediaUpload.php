<?php

namespace App\Filament\Forms;

use App\Domain\Catalog\Models\Media;
use App\Domain\Catalog\Services\MediaUploader;
use Filament\Forms\Components\FileUpload;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Campo de imagen que guarda un registro de Media (id) en lugar de una ruta:
 * pasa por MediaUploader (valida tipo real, quita metadatos, genera miniatura).
 */
final class MediaUpload
{
    public static function make(string $name, string $label, string $folder = 'catalog'): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->image()
            ->disk('local')
            ->directory('tmp-uploads')
            ->acceptedFileTypes(array_keys(MediaUploader::ALLOWED))
            ->maxSize(MediaUploader::MAX_BYTES / 1024)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => (string) app(MediaUploader::class)
                ->store($file->getRealPath(), $file->getClientOriginalName(), null, $folder)->id)
            ->getUploadedFileUsing(function (string $file) {
                $media = Media::query()->find($file);

                return $media ? [
                    'name' => $media->original_name ?? basename($media->path),
                    'size' => $media->size,
                    'type' => $media->mime,
                    'url' => $media->thumbUrl(),
                ] : null;
            })
            // Quitar la imagen del formulario no borra el archivo: puede usarse en otros lugares
            ->deleteUploadedFileUsing(fn () => null);
    }
}
