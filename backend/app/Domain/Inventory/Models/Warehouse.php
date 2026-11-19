<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Shipping\Models\Region;
use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    use Auditable;

    public const STATUSES = ['active' => 'Activa', 'inactive' => 'Inactiva'];

    protected $fillable = ['name', 'code', 'address', 'region_id', 'commune', 'phone', 'manager_user_id', 'status', 'priority', 'is_default'];

    protected $attributes = ['status' => 'active', 'priority' => 0, 'is_default' => false];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'priority' => 'integer'];
    }

    public static function default(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->active()->orderBy('priority')->orderBy('id')->firstOrFail();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }
}
