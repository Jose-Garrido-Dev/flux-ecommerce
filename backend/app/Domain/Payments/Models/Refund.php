<?php

namespace App\Domain\Payments\Models;

use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasUuids;

    protected $fillable = ['payment_id', 'order_id', 'return_id', 'amount', 'reason', 'status', 'external_type', 'authorization_code', 'restocked', 'created_by'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['amount' => 'integer', 'restocked' => 'boolean'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
