<?php

namespace App\Domain\System\Services;

use App\Domain\System\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Acceso a la configuración editable de la tienda, con caché.
 * Nunca guardar aquí credenciales: esas van cifradas (Fase 6).
 */
class SettingsRepository
{
    private const CACHE_KEY = 'flux.settings.';

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        return Cache::rememberForever(self::CACHE_KEY.$group, fn () => Setting::query()
            ->where('group', $group)
            ->pluck('value', 'key')
            ->all());
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->group($group)[$key] ?? $default;
    }

    /** @param array<string, mixed> $values */
    public function putMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = Setting::query()->firstOrNew(['group' => $group, 'key' => $key]);
            $setting->value = $value;
            $setting->save();
        }

        Cache::forget(self::CACHE_KEY.$group);
    }
}
