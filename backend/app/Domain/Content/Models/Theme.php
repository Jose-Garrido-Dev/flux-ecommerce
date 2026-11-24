<?php

namespace App\Domain\Content\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Tema visual de la tienda: paquete de tokens (colores, tipografía, formas). Solo uno activo. */
class Theme extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'description', 'tokens', 'overrides', 'is_active'];

    protected function casts(): array
    {
        return ['tokens' => 'array', 'overrides' => 'array', 'is_active' => 'boolean'];
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->first();
    }
}
