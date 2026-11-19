<?php

namespace App\Domain\System\Concerns;

use App\Domain\System\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/** Registra en auditoría la creación, modificación y eliminación del modelo. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => app(AuditLogger::class)->record(
            class_basename($m).'.created', $m, [], $m->getAttributes(),
        ));

        static::updated(function (Model $m) {
            $changes = $m->getChanges();
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($m->getOriginal(), $changes);
            app(AuditLogger::class)->record(class_basename($m).'.updated', $m, $old, $changes);
        });

        static::deleted(fn (Model $m) => app(AuditLogger::class)->record(
            class_basename($m).'.deleted', $m, $m->getOriginal(), [],
        ));
    }
}
