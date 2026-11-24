<?php

namespace App\Domain\Marketing\Models;

use App\Domain\Customers\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSubscriber extends Model
{
    public const STATUSES = ['pending' => 'Por confirmar', 'subscribed' => 'Suscrito', 'unsubscribed' => 'Dado de baja'];

    public const SOURCES = ['footer' => 'Formulario de la tienda', 'checkout' => 'Compra', 'account' => 'Mi cuenta', 'register' => 'Registro', 'admin' => 'Panel'];

    protected $fillable = [
        'email', 'name', 'customer_id', 'source', 'status', 'confirm_token_hash', 'confirm_sent_at', 'confirmed_at', 'unsubscribed_at', 'unsubscribe_reason',
    ];

    protected $attributes = ['status' => 'pending'];

    protected $hidden = ['confirm_token_hash'];

    protected function casts(): array
    {
        return ['confirm_sent_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function scopeSubscribed(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
