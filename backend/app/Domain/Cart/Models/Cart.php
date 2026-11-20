<?php

namespace App\Domain\Cart\Models;

use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use HasUuids;

    protected $fillable = ['customer_id', 'token_hash', 'email', 'currency', 'status', 'last_activity_at'];

    protected $attributes = ['status' => 'active', 'currency' => 'CLP'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
