<?php

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Registro de auditoría de solo inserción. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'result', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de auditoría no se modifican.'));
        static::deleting(fn () => throw new LogicException('Los registros de auditoría no se eliminan.'));
    }
}
