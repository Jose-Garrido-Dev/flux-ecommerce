<?php

namespace App\Filament\Support;

use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Validation\ValidationException;

/** Muestra los errores de un servicio en el formulario de la página (no en un modal) y detiene el guardado. */
final class FormErrors
{
    /**
     * @param  string  $prefix  ruta del estado del formulario (data)
     * @param  array<string, string>  $map  clave del servicio => campo del formulario
     */
    public static function run(Closure $callback, string $prefix = 'data', array $map = [], string $title = 'No se pudo guardar'): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title($title)->body(collect($e->errors())->flatten()->take(6)->implode("\n"))->persistent()->send();
            $fields = collect($e->errors())->filter(fn ($m, $key) => ! str_starts_with($key, 'blocks.') || isset($map[$key]))
                ->mapWithKeys(fn ($m, $key) => [$prefix.'.'.($map[$key] ?? $key) => $m])->all();
            if ($fields !== []) {
                throw ValidationException::withMessages($fields);
            }
            throw new Halt;
        }
    }
}
