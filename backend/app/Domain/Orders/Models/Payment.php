<?php

namespace App\Domain\Orders\Models;

use App\Domain\Payments\Models\PaymentTransaction;
use App\Domain\Payments\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasUuids;

    public const METHODS = ['bank_transfer' => 'Transferencia bancaria', 'webpay_plus' => 'Webpay'];

    public const STATUSES = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'failed' => 'Rechazado', 'aborted' => 'No completado', 'expired' => 'Vencido', 'cancelled' => 'Anulado'];

    protected $fillable = ['order_id', 'method_code', 'attempt', 'amount', 'currency', 'status', 'external_id', 'gateway_order', 'reference', 'expires_at', 'confirmed_at', 'confirmed_by',
        'authorization_code', 'card_last4', 'payment_type', 'installments', 'failure_reason', 'refunded_amount'];

    protected $attributes = ['status' => 'pending', 'currency' => 'CLP'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'refunded_amount' => 'integer', 'attempt' => 'integer', 'installments' => 'integer', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method_code] ?? $this->method_code;
    }

    public function refundable(): int
    {
        return $this->status === 'paid' ? max(0, $this->amount - $this->refunded_amount) : 0;
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
