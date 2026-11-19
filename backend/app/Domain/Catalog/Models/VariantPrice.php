<?php

namespace App\Domain\Catalog\Models;

use App\Domain\System\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/** Precio de una variante. Montos en unidades mínimas (CLP sin decimales), IVA incluido. */
class VariantPrice extends Model
{
    use Auditable;

    protected $fillable = [
        'variant_id', 'currency', 'price_amount', 'compare_at_amount', 'cost_amount',
        'sale_amount', 'sale_starts_at', 'sale_ends_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (VariantPrice $p) {
            if ($p->compare_at_amount !== null && $p->compare_at_amount <= $p->price_amount) {
                throw new InvalidArgumentException('El precio "antes" debe ser mayor que el precio.');
            }
            if ($p->sale_amount !== null && $p->sale_amount >= $p->price_amount) {
                throw new InvalidArgumentException('El precio de oferta debe ser menor que el precio.');
            }
            if ($p->sale_starts_at && $p->sale_ends_at && $p->sale_ends_at->lte($p->sale_starts_at)) {
                throw new InvalidArgumentException('La oferta debe terminar después de comenzar.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_amount' => 'integer',
            'cost_amount' => 'integer',
            'sale_amount' => 'integer',
            'sale_starts_at' => 'datetime',
            'sale_ends_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function saleIsActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->sale_amount !== null
            && ($this->sale_starts_at === null || $this->sale_starts_at->lte($at))
            && ($this->sale_ends_at === null || $this->sale_ends_at->gt($at));
    }

    /** Precio que paga el cliente ahora. */
    public function effectiveAmount(?CarbonInterface $at = null): int
    {
        return $this->saleIsActive($at) ? $this->sale_amount : $this->price_amount;
    }

    /**
     * Precio tachado que se muestra junto al efectivo, o null si no hay descuento.
     * Con oferta vigente se tacha el precio normal (o el "antes" si es mayor).
     */
    public function strikethroughAmount(?CarbonInterface $at = null): ?int
    {
        if ($this->saleIsActive($at)) {
            return max($this->price_amount, $this->compare_at_amount ?? 0);
        }

        return $this->compare_at_amount;
    }
}
