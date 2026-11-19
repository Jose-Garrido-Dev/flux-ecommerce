<?php

namespace App\Domain\Pricing\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class TaxClass extends Model
{
    use Auditable;

    protected $fillable = ['code', 'name', 'rate_bp', 'is_exempt', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'rate_bp' => 'integer',
            'is_exempt' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Tasa efectiva en puntos base (0 si es exento). */
    public function effectiveRateBp(): int
    {
        return $this->is_exempt ? 0 : $this->rate_bp;
    }
}
