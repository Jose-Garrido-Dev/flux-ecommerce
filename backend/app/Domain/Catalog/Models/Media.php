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

    protected $fillable = ['disk', 'path', 'original_name', 'mime', 'size', 'width', 'height', 'alt', 'title', 'checksum', 'variants', 'uploaded_by'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['size' => 'integer', 'width' => 'integer', 'height' => 'integer', 'variants' => 'array'];
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /** URL de la miniatura (si se generó) o del original. Para el panel. */
    public function thumbUrl(): string
    {
        $thumb = $this->variants['thumb'] ?? null;

        return Storage::disk($this->disk)->url($thumb ?? $this->path);
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
