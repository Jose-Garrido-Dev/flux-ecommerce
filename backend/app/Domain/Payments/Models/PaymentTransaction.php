<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Cada llamada a una pasarela y su respuesta, sin datos de tarjeta. Solo se agregan filas. */
class PaymentTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'create' => 'Inicio del pago', 'return' => 'Regreso del cliente', 'commit' => 'Confirmación', 'status' => 'Consulta de estado',
        'refund' => 'Reembolso', 'verify' => 'Prueba de conexión',
    ];

    public const RESULTS = ['ok' => 'Correcto', 'rejected' => 'Rechazado', 'error' => 'Error'];

    protected $fillable = ['payment_id', 'gateway', 'type', 'external_id', 'result', 'http_status', 'request', 'response'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El registro de transacciones de pago no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El registro de transacciones de pago no se puede eliminar.'));
    }

    protected function casts(): array
    {
        return ['request' => 'array', 'response' => 'array', 'created_at' => 'datetime'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
