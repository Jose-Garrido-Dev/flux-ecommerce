<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Content\Models\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Temas: carga los incluidos, activa uno y aplica ajustes de color validando el contraste. */
class ThemeService
{
    public const OVERRIDABLE = ['accent', 'accent-fg', 'field', 'field-fg', 'dark.accent', 'dark.accent-fg'];

    /** Crea o actualiza los temas incluidos sin tocar los ajustes de cada tienda. Activa Lapislázuli si no hay ninguno. */
    public function syncPresets(): void
    {
        foreach (ThemePresets::all() as $code => $preset) {
            Theme::query()->updateOrCreate(['code' => $code], ['name' => $preset['name'], 'description' => $preset['description'], 'tokens' => $preset['tokens']]);
        }
        if (! Theme::query()->where('is_active', true)->exists()) {
            Theme::query()->where('code', 'lapislazuli')->update(['is_active' => true]);
        }
    }

    public function activate(Theme $theme): void
    {
        DB::transaction(function () use ($theme) {
            Theme::query()->where('is_active', true)->whereKeyNot($theme->id)->update(['is_active' => false]);
            $theme->update(['is_active' => true]);
        });
        RevalidateStorefront::dispatch(['store']);
    }

    /** @param  array<string, ?string>  $overrides  clave => #rrggbb (vacío = valor del tema) */
    public function saveOverrides(Theme $theme, array $overrides): void
    {
        $clean = [];
        foreach (self::OVERRIDABLE as $key) {
            $value = strtolower(trim((string) ($overrides[$key] ?? '')));
            if ($value === '') {
                continue;
            }
            if (! preg_match('/^#[0-9a-f]{6}$/', $value)) {
                throw ValidationException::withMessages(["overrides.$key" => 'Usa un color en formato #rrggbb.']);
            }
            $clean[$key] = $value;
        }
        if ($problems = ThemePresets::contrastProblems($this->merge($theme->tokens, $clean))) {
            throw ValidationException::withMessages(['overrides' => 'Con esos colores el texto no se lee bien. '.implode(' ', $problems)]);
        }
        $theme->update(['overrides' => $clean ?: null]);
        if ($theme->is_active) {
            RevalidateStorefront::dispatch(['store']);
        }
    }

    /** Tokens finales (tema + ajustes). */
    public function tokens(Theme $theme): array
    {
        return $this->merge($theme->tokens, $theme->overrides ?? []);
    }

    private function merge(array $tokens, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (str_starts_with($key, 'dark.')) {
                $tokens['dark'][substr($key, 5)] = $value;
            } else {
                $tokens['light'][$key] = $value;
            }
        }

        return $tokens;
    }

    /** Lo que recibe la tienda: solo claves conocidas y colores válidos. */
    public function publicTheme(): ?array
    {
        $theme = Theme::active();
        if (! $theme) {
            return null;
        }
        $tokens = $this->tokens($theme);
        $hex = fn (array $colors, array $keys) => collect($keys)->mapWithKeys(fn ($k) => [$k => $colors[$k] ?? null])
            ->filter(fn ($v) => is_string($v) && preg_match('/^#[0-9a-f]{6}$/i', $v))->all();

        return [
            'code' => $theme->code,
            'light' => $hex($tokens['light'] ?? [], ThemePresets::COLOR_KEYS),
            'dark' => $hex($tokens['dark'] ?? [], ThemePresets::DARK_KEYS),
            'font_display' => array_key_exists($tokens['font_display'] ?? '', ThemePresets::FONTS) ? $tokens['font_display'] : 'brand',
            'radius_sm' => max(0, min(24, (int) ($tokens['radius_sm'] ?? 4))),
            'radius' => max(0, min(32, (int) ($tokens['radius'] ?? 8))),
            'uppercase_headings' => (bool) ($tokens['uppercase_headings'] ?? false),
        ];
    }
}
