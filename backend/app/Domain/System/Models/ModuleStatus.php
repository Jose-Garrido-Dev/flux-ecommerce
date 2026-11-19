<?php

namespace App\Domain\System\Models;

use App\Domain\System\Concerns\Auditable;
use App\Domain\System\Enums\ModuleState;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleStatus extends Model
{
    use Auditable;

    protected $fillable = ['number', 'name', 'phase', 'state', 'evidence_url', 'note', 'updated_by'];

    protected function casts(): array
    {
        return ['state' => ModuleState::class];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
