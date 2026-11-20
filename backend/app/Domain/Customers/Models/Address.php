<?php

namespace App\Domain\Customers\Models;

use App\Domain\Shipping\Models\Commune;
use App\Domain\Shipping\Models\Region;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $fillable = ['customer_id', 'label', 'recipient', 'phone', 'street', 'number', 'apartment', 'region_id', 'commune_id', 'commune', 'reference', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function communeRecord(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'commune_id');
    }

    /** Forma pública (tienda y copia en el pedido). */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient' => $this->recipient,
            'phone' => $this->phone,
            'street' => $this->street,
            'number' => $this->number,
            'apartment' => $this->apartment,
            'region_code' => $this->region?->code,
            'region' => $this->region?->name,
            'commune' => $this->commune,
            'reference' => $this->reference,
            'is_default' => $this->is_default,
        ];
    }
}
