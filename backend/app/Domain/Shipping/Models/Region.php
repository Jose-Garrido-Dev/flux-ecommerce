<?php

namespace App\Domain\Shipping\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use Auditable;

    protected $fillable = ['code', 'roman', 'position', 'name', 'is_enabled'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'position' => 'integer'];
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }
}
