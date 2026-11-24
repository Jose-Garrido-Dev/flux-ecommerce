<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['automation_id', 'subject_type', 'subject_id', 'email', 'status', 'error'];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
