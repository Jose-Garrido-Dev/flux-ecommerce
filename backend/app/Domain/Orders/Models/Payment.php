<?php

namespace App\Domain\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;

    public const METHODS = ['bank_transfer' => 'Transferencia bancaria'];

    public const STATUSES = ['pending' => 'Pendiente', 'paid' => 'Pagado', 'expired' => 'Vencido', 'cancelled' => 'Anulado'];

    protected $fillable = ['order_id', 'method_code', 'amount', 'currency', 'status', 'external_id', 'reference', 'expires_at', 'confirmed_at', 'confirmed_by'];

    protected $attributes = ['status' => 'pending', 'currency' => 'CLP'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'expires_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
