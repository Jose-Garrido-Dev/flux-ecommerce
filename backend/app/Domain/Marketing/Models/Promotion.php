<?php

namespace App\Domain\Marketing\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use Auditable;

    public const TYPES = [
        'cart_amount' => 'Descuento por monto de compra',
        'collection' => 'Descuento en categorías, marcas o productos',
        'qty_discount' => 'Descuento por cantidad',
        'buy_x_get_y' => 'Lleva X y paga menos (por ejemplo, 3x2)',
    ];

    protected $fillable = ['name', 'type', 'conditions', 'actions', 'priority', 'stackable', 'starts_at', 'ends_at', 'status'];

    protected $attributes = ['status' => 'active', 'stackable' => true, 'priority' => 0];

    protected function casts(): array
    {
        return ['conditions' => 'array', 'actions' => 'array', 'priority' => 'integer', 'stackable' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeRunning(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', 'active')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}
