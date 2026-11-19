<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Guarda imágenes subidas desde el panel (módulo 2, pestaña C).
 *
 * - Valida el tipo REAL del archivo (no la extensión) y su tamaño.
 * - Vuelve a codificar la imagen con GD: elimina metadatos (EXIF con ubicación)
 *   y cualquier contenido incrustado que no sea imagen.
 * - Limita el lado mayor a 2400 px y genera una miniatura WebP de 480 px.
 * - Si la misma imagen ya existe (mismo contenido), la reutiliza.
 * Todo queda en el disco del servidor; no usa servicios externos.
 */
class MediaUploader
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    public const MAX_SIDE = 2400;

    public const THUMB_SIDE = 480;

    public const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    /**
     * @param  string  $sourcePath  Ruta absoluta del archivo temporal
     */
    public function store(string $sourcePath, ?string $originalName = null, ?string $alt = null, string $folder = 'catalog'): Media
    {
        $bytes = @file_get_contents($sourcePath);
        if ($bytes === false || $bytes === '') {
            $this->fail('No se pudo leer el archivo.');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            $this->fail('La imagen supera el máximo de 10 MB.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! isset(self::ALLOWED[$mime])) {
            $this->fail('El archivo no es una imagen válida (se aceptan JPG, PNG, WebP y GIF).');
        }

        $checksum = hash('sha256', $bytes);
        if ($existing = Media::query()->where('checksum', $checksum)->first()) {
            return $existing;
        }

        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            $this->fail('La imagen está dañada o no se puede procesar.');
        }

        $image = $this->orient($image, $bytes, $mime);
        $image = $this->fit($image, self::MAX_SIDE);
        $hasAlpha = in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);

        // JPEG para fotos; WebP si tiene transparencia (los PNG con fondo transparente lo conservan)
        [$encoded, $outMime, $ext] = $hasAlpha
            ? [$this->encode($image, 'webp'), 'image/webp', 'webp']
            : [$this->encode($image, 'jpeg'), 'image/jpeg', 'jpg'];

        $name = Str::lower((string) Str::ulid());
        $path = "$folder/$name.$ext";
        $thumbPath = "$folder/thumbs/$name.webp";

        $disk = Storage::disk('public');
        $disk->put($path, $encoded);
        $disk->put($thumbPath, $this->encode($this->fit($image, self::THUMB_SIDE), 'webp', 78));

        return Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $originalName ? Str::limit(basename($originalName), 250, '') : null,
            'mime' => $outMime,
            'size' => strlen($encoded),
            'width' => imagesx($image),
            'height' => imagesy($image),
            'alt' => $alt,
            'checksum' => $checksum,
            'variants' => ['thumb' => $thumbPath],
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function fit(GdImage $image, int $max): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $max) {
            return $image;
        }
        $ratio = $max / max($w, $h);
        $nw = max(1, (int) round($w * $ratio));
        $nh = max(1, (int) round($h * $ratio));

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    /** Las fotos de celular traen la rotación en EXIF; se aplica antes de borrar los metadatos. */
    private function orient(GdImage $image, string $bytes, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes));
        $angle = match ($exif['Orientation'] ?? 1) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? (imagerotate($image, $angle, 0) ?: $image) : $image;
    }

    private function encode(GdImage $image, string $format, int $quality = 84): string
    {
        ob_start();
        if ($format === 'webp') {
            imagesavealpha($image, true);
            imagewebp($image, null, $quality);
        } else {
            imageinterlace($image, true);
            imagejpeg($image, null, $quality);
        }

        return (string) ob_get_clean();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
