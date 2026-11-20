<?php

namespace App\Domain\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Línea de tiempo del pedido. Solo se agregan eventos. */
class OrderEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'placed' => 'Pedido creado',
        'payment_confirmed' => 'Pago confirmado',
        'payment_expired' => 'Plazo de pago vencido',
        'payment_failed' => 'Pago rechazado',
        'payment_aborted' => 'Pago no completado',
        'refunded' => 'Reembolso',
        'cancelled' => 'Pedido cancelado',
        'note' => 'Nota',
        'email_sent' => 'Correo enviado',
    ];

    protected $fillable = ['order_id', 'type', 'from_status', 'to_status', 'actor_type', 'actor_id', 'payload', 'visible_to_customer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial del pedido no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El historial del pedido no se puede eliminar.'));
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'visible_to_customer' => 'boolean', 'created_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
