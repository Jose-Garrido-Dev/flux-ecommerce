<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/** Movimiento de inventario. Inmutable: se corrige con un contramovimiento. */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'variant_id', 'warehouse_id', 'type', 'quantity', 'before_on_hand', 'after_on_hand', 'reserved_change',
        'reason', 'reference_type', 'reference_id', 'user_id', 'reverses_movement_id', 'stock_transfer_id',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos de inventario no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los movimientos de inventario no se pueden eliminar.'));
    }

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'integer',
            'before_on_hand' => 'integer',
            'after_on_hand' => 'integer',
            'reserved_change' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_movement_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_movement_id');
    }

    public function isReversible(): bool
    {
        return $this->quantity !== 0
            && ! in_array($this->type, [MovementType::Reversal, MovementType::TransferIn, MovementType::TransferOut, MovementType::SaleOut], true)
            && ! $this->reversal()->exists();
    }
}
