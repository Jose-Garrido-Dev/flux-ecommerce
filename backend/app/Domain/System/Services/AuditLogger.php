<?php

namespace App\Domain\System\Services;

use App\Domain\System\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    /** Claves que nunca se guardan en auditoría (regla: no registrar secretos). */
    private const REDACTED = [
        'password', 'remember_token', 'api_key', 'secret', 'token', 'credentials',
        'mfa_secret', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    public function record(
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        string $result = 'success',
    ): AuditLog {
        $user = Auth::user();
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'actor_type' => $user ? 'user' : 'system',
            'actor_id' => $user?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->redact($old) ?: null,
            'new_values' => $this->redact($new) ?: null,
            'result' => $result,
            'ip' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            foreach (self::REDACTED as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $values[$key] = '[oculto]';

                    continue 2;
                }
            }
            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
