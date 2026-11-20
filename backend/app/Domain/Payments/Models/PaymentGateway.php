<?php

namespace App\Domain\Payments\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuración de una pasarela. Las credenciales se guardan cifradas con APP_KEY.
 * Una pasarela solo se ofrece a los clientes si está activa y verificada contra el ambiente
 * real (integración o producción) con las mismas credenciales que tiene guardadas.
 */
class PaymentGateway extends Model
{
    use Auditable;

    public const ENVIRONMENTS = ['integration' => 'Integración (pruebas)', 'production' => 'Producción'];

    protected $fillable = ['code', 'name', 'enabled', 'environment', 'credentials', 'verified_at', 'verified_environment', 'verified_fingerprint', 'last_error', 'position'];

    protected $hidden = ['credentials'];

    /** La auditoría nunca registra las credenciales. */
    public array $auditExclude = ['credentials'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
        ];
    }

    public static function for(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }

    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /** Huella de ambiente + credenciales: si cambian, la verificación anterior deja de valer. */
    public function fingerprint(): string
    {
        $creds = $this->credentials ?? [];
        ksort($creds);

        return hash_hmac('sha256', $this->environment.'|'.json_encode($creds), (string) config('app.key'));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null
            && $this->verified_environment === $this->environment
            && $this->verified_fingerprint !== null
            && hash_equals($this->verified_fingerprint, $this->fingerprint());
    }

    /** Se ofrece en el checkout. */
    public function isAvailable(): bool
    {
        return $this->enabled && $this->isVerified();
    }

    public function isTestMode(): bool
    {
        return $this->environment !== 'production';
    }

    public function statusLabel(): string
    {
        if (! $this->isVerified()) {
            return 'No verificada';
        }
        $where = $this->environment === 'production' ? 'en producción' : 'en integración';

        return $this->enabled ? "Activa, verificada {$where}" : "Verificada {$where}, apagada";
    }
}
