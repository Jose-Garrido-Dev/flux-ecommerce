<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageVersion;
use App\Domain\System\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo de vida de una página: guardar crea una versión nueva (las versiones no se modifican),
 * publicar apunta la página a una versión, restaurar copia una versión anterior como versión nueva,
 * y la vista previa usa un enlace firmado que vence.
 */
class PageService
{
    public const PREVIEW_MINUTES = 120;

    public function create(array $data, array $blocks = []): Page
    {
        $data = $this->attributes($data);

        return DB::transaction(function () use ($data, $blocks) {
            $page = Page::query()->create([...$data, 'status' => 'draft', 'updated_by' => Auth::id()]);
            $this->saveVersion($page, $blocks, 'Versión inicial');

            return $page;
        });
    }

    public function update(Page $page, array $data): Page
    {
        $data = $this->attributes($data, $page);
        $oldSlug = $page->slug;
        $page->update([...$data, 'updated_by' => Auth::id()]);
        if ($page->status === 'published') {
            $this->revalidate($page, $oldSlug);
        }

        return $page;
    }

    /** Guarda los bloques como versión nueva. Si no cambió nada, devuelve la última. */
    public function saveVersion(Page $page, array $blocks, ?string $note = null, ?int $restoredFrom = null): PageVersion
    {
        $blocks = BlockSchema::validate($blocks);

        return DB::transaction(function () use ($page, $blocks, $note, $restoredFrom) {
            Page::query()->whereKey($page->id)->lockForUpdate()->first();
            $latest = PageVersion::query()->where('page_id', $page->id)->orderByDesc('number')->first();
            if ($latest && $restoredFrom === null && $latest->blocks == $blocks) {
                return $latest;
            }
            $version = PageVersion::query()->create([
                'page_id' => $page->id, 'number' => ($latest->number ?? 0) + 1, 'blocks' => $blocks,
                'note' => $note ? Str::limit($note, 200, '') : null, 'created_by' => Auth::id(), 'restored_from' => $restoredFrom,
            ]);
            $page->forceFill(['updated_by' => Auth::id()])->touch();

            return $version;
        });
    }

    public function publish(Page $page, ?PageVersion $version = null): Page
    {
        $version ??= $page->latestVersion()->first();
        if (! $version || $version->page_id !== $page->id) {
            throw ValidationException::withMessages(['version' => 'La versión no pertenece a esta página.']);
        }
        if ($version->blocks === []) {
            throw ValidationException::withMessages(['blocks' => 'Agrega al menos un bloque antes de publicar.']);
        }
        // Lo referenciado (imágenes, productos) puede haberse borrado desde que se guardó
        BlockSchema::validate($version->blocks);
        $before = $page->published_version_id;
        $page->update(['status' => 'published', 'published_version_id' => $version->id, 'published_at' => now(), 'updated_by' => Auth::id()]);
        app(AuditLogger::class)->record('Page.published', $page, ['version' => $before], ['version' => $version->number]);
        $this->revalidate($page);

        return $page;
    }

    public function unpublish(Page $page): Page
    {
        $page->update(['status' => 'draft', 'published_version_id' => null, 'updated_by' => Auth::id()]);
        $this->revalidate($page);

        return $page;
    }

    /** Copia una versión anterior como la versión más nueva (sin publicarla). */
    public function restore(Page $page, PageVersion $version): PageVersion
    {
        if ($version->page_id !== $page->id) {
            throw ValidationException::withMessages(['version' => 'La versión no pertenece a esta página.']);
        }

        return $this->saveVersion($page, $version->blocks, 'Restaurada desde la versión '.$version->number, $version->number);
    }

    public function previewToken(Page $page, ?PageVersion $version = null): string
    {
        $version ??= $page->latestVersion()->first();
        $payload = rtrim(strtr(base64_encode(json_encode(['p' => $page->id, 'v' => $version?->id, 'e' => now()->addMinutes(self::PREVIEW_MINUTES)->timestamp])), '+/', '-_'), '=');

        return $payload.'.'.$this->sign($payload);
    }

    /** @return array{0: Page, 1: PageVersion}|null */
    public function fromPreviewToken(string $token): ?array
    {
        [$payload, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if ($payload === '' || ! hash_equals($this->sign($payload), $sig)) {
            return null;
        }
        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/')), true);
        if (! is_array($data) || ($data['e'] ?? 0) < now()->timestamp) {
            return null;
        }
        $page = Page::query()->find($data['p'] ?? 0);
        $version = $page ? PageVersion::query()->where('page_id', $page->id)->find($data['v'] ?? 0) : null;

        return $page && $version ? [$page, $version] : null;
    }

    public function previewUrl(Page $page, ?PageVersion $version = null): string
    {
        return rtrim((string) config('flux.storefront_url'), '/').'/vista-previa/'.$this->previewToken($page, $version);
    }

    private function sign(string $payload): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', 'page-preview|'.$payload, (string) config('app.key'), true)), '+/', '-_'), '=');
    }

    private function attributes(array $data, ?Page $page = null): array
    {
        $type = $data['type'] ?? $page?->type ?? 'institutional';
        $slug = Str::slug((string) ($data['slug'] ?? '') ?: (string) ($data['title'] ?? ''));
        $errors = [];
        if (blank($data['title'] ?? $page?->title)) {
            $errors['title'] = 'Escribe el título de la página.';
        }
        if (! array_key_exists($type, Page::TYPES)) {
            $errors['type'] = 'Tipo de página no válido.';
        }
        if ($type === 'home') {
            $slug = 'portada';
            if (Page::query()->where('type', 'home')->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists()) {
                $errors['type'] = 'Ya existe una portada. Edítala o cambia su tipo antes de crear otra.';
            }
        } elseif ($slug === '' || in_array($slug, Page::RESERVED_SLUGS, true)) {
            $errors['slug'] = $slug === '' ? 'Escribe la dirección de la página.' : "La dirección «{$slug}» la usa la tienda. Elige otra.";
        }
        if ($slug !== '' && Page::query()->where('slug', $slug)->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists()) {
            $errors['slug'] = "Ya existe una página con la dirección «{$slug}».";
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return array_filter([
            'title' => isset($data['title']) ? trim((string) $data['title']) : null,
            'slug' => $slug,
            'type' => $type,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
        ], fn ($v, $k) => $v !== null || in_array($k, ['seo_title', 'seo_description'], true), ARRAY_FILTER_USE_BOTH);
    }

    private function revalidate(Page $page, ?string $oldSlug = null): void
    {
        $tags = ['pages', 'page:'.$page->slug];
        if ($oldSlug && $oldSlug !== $page->slug) {
            $tags[] = 'page:'.$oldSlug;
        }
        if ($page->type === 'home') {
            $tags[] = 'home';
        }
        RevalidateStorefront::dispatch($tags);
    }
}
