<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Content\Models\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Redirecciones 301/302 de la tienda. Evita ciclos, aplana cadenas (A→B y B→C queda A→C y B→C)
 * y crea redirecciones automáticas cuando cambia la dirección de un contenido.
 */
class RedirectService
{
    public const MAX = 5000;

    /** Rutas de la tienda que no se pueden redirigir. */
    private const PROTECTED = ['/api', '/_next', '/media', '/vista-previa', '/admin'];

    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('#^https?://[^/]+(/.*)?$#i', $path, $m)) {
            $path = $m[1] ?? '/';
        }
        [$p, $query] = array_pad(explode('?', $path, 2), 2, null);
        $p = '/'.ltrim(preg_replace('#/+#', '/', $p), '/');
        $p = $p === '/' ? '/' : rtrim($p, '/');

        return $query !== null && $query !== '' ? "$p?$query" : $p;
    }

    public function save(?Redirect $redirect, string $from, string $to, int $code = 301, bool $automatic = false): Redirect
    {
        $from = self::normalize($from);
        $to = str_starts_with(trim($to), '/') ? self::normalize($to) : trim($to);
        $errors = [];
        if ($from === '/' || mb_strlen($from) > 300) {
            $errors['from_path'] = 'Indica la dirección antigua, por ejemplo /tienda/polera-negra.';
        } elseif (collect(self::PROTECTED)->contains(fn ($p) => $from === $p || str_starts_with($from, "$p/"))) {
            $errors['from_path'] = 'Esa dirección la usa el sistema y no se puede redirigir.';
        }
        if (! BlockSchema::validLink($to) || mb_strlen($to) > 500) {
            $errors['to_path'] = 'El destino debe ser una ruta de la tienda (/catalogo) o una dirección https://.';
        } elseif ($to === $from) {
            $errors['to_path'] = 'El destino no puede ser la misma dirección.';
        }
        if (! in_array($code, [301, 302], true)) {
            $errors['code'] = 'Elige permanente (301) o temporal (302).';
        }
        if ($errors === [] && Redirect::query()->where('from_path', $from)->when($redirect, fn ($q) => $q->whereKeyNot($redirect->id))->exists()) {
            $errors['from_path'] = 'Ya existe una redirección desde esa dirección.';
        }
        if ($errors === [] && $this->createsLoop($from, $to, $redirect?->id)) {
            $errors['to_path'] = 'Esa redirección formaría un ciclo: el destino termina volviendo a la dirección antigua.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if (! $redirect && Redirect::query()->count() >= self::MAX) {
            throw ValidationException::withMessages(['from_path' => 'Se alcanzó el máximo de '.self::MAX.' redirecciones.']);
        }

        $saved = DB::transaction(function () use ($redirect, $from, $to, $code, $automatic) {
            // Si el destino ya redirige a otro lado, se apunta directo al final de la cadena
            $final = $this->finalDestination($to);
            $redirect ??= new Redirect;
            $redirect->fill(['from_path' => $from, 'to_path' => $final, 'code' => $code, 'is_automatic' => $automatic])->save();
            // Las que apuntaban a la dirección antigua ahora apuntan directo al destino
            Redirect::query()->where('to_path', $from)->whereKeyNot($redirect->id)->update(['to_path' => $final, 'updated_at' => now()]);

            return $redirect;
        });
        $this->revalidate();

        return $saved;
    }

    public function delete(Redirect $redirect): void
    {
        $redirect->delete();
        $this->revalidate();
    }

    /** Cambio de dirección de un contenido: la antigua redirige a la nueva. */
    public function slugChanged(string $oldPath, string $newPath): void
    {
        if ($oldPath === $newPath || $oldPath === '/') {
            return;
        }
        // La dirección nueva ahora tiene contenido: deja de redirigir
        Redirect::query()->where('from_path', $newPath)->delete();
        $existing = Redirect::query()->where('from_path', $oldPath)->first();
        try {
            $this->save($existing, $oldPath, $newPath, 301, automatic: true);
        } catch (ValidationException) {
            // Una dirección protegida o un ciclo no deben impedir guardar el contenido
        }
    }

    /** @return list<array{from: string, to: string, code: int}> */
    public function all(): array
    {
        return Redirect::query()->orderBy('from_path')->limit(self::MAX)->get(['from_path', 'to_path', 'code'])
            ->map(fn (Redirect $r) => ['from' => $r->from_path, 'to' => $r->to_path, 'code' => $r->code])->all();
    }

    public function hit(string $path): bool
    {
        return Redirect::query()->where('from_path', self::normalize($path))
            ->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]) > 0;
    }

    private function createsLoop(string $from, string $to, ?int $ignoreId): bool
    {
        $seen = [$from => true];
        $current = $to;
        for ($i = 0; $i < 20 && str_starts_with($current, '/'); $i++) {
            if (isset($seen[$current])) {
                return true;
            }
            $seen[$current] = true;
            $next = Redirect::query()->where('from_path', $current)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->value('to_path');
            if ($next === null) {
                return false;
            }
            $current = $next;
        }

        return isset($seen[$current]);
    }

    private function finalDestination(string $to): string
    {
        $current = $to;
        for ($i = 0; $i < 20 && str_starts_with($current, '/'); $i++) {
            $next = Redirect::query()->where('from_path', $current)->value('to_path');
            if ($next === null) {
                break;
            }
            $current = $next;
        }

        return $current;
    }

    private function revalidate(): void
    {
        RevalidateStorefront::dispatch(['redirects']);
    }
}
