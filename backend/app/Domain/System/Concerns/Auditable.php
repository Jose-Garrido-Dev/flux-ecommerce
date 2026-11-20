<?php

namespace App\Domain\System\Concerns;

use App\Domain\System\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra en auditoría la creación, modificación y eliminación del modelo.
 * Los campos listados en la propiedad pública $auditExclude (p. ej. credenciales) se registran como "[oculto]".
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => app(AuditLogger::class)->record(
            class_basename($m).'.created', $m, [], static::auditSafe($m, $m->getAttributes()),
        ));

        static::updated(function (Model $m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            app(AuditLogger::class)->record(class_basename($m).'.updated', $m, static::auditSafe($m, $old), static::auditSafe($m, $changes));
        });

        static::deleted(fn (Model $m) => app(AuditLogger::class)->record(
            class_basename($m).'.deleted', $m, static::auditSafe($m, $m->getOriginal()), [],
        ));
    }

    protected static function auditSafe(Model $m, array $values): array
    {
        foreach ($m->auditExclude ?? [] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[oculto]';
            }
        }

        return $values;
    }
}
