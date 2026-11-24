<?php

namespace App\Domain\Shipping\Services;

use Illuminate\Support\Str;

/** Compara comunas por nombre sin importar mayúsculas, tildes ni espacios ("Ñuñoa" = "nunoa"). */
final class CommuneName
{
    public static function normalize(?string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $name))));
    }
}
