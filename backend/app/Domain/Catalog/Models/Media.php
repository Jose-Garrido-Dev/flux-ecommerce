<?php

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Archivo subido (imagen, documento). Se guarda en el disco del servidor por defecto. */
class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $fillable = ['disk', 'path', 'mime', 'size', 'width', 'height', 'alt', 'title', 'checksum', 'uploaded_by'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** Datos públicos para el storefront. `path` es relativo: el storefront lo sirve en /media/{path}. */
    public function toPublicArray(?string $fallbackAlt = null): array
    {
        return [
            'path' => $this->path,
            'alt' => $this->alt ?: ($fallbackAlt ?? ''),
            'width' => $this->width,
            'height' => $this->height,
        ];
    }
}
