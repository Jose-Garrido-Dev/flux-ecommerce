<?php

namespace App\Domain\Content\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Versión inmutable de una página: los bloques tal como se guardaron. */
class PageVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['page_id', 'number', 'blocks', 'note', 'created_by', 'restored_from'];

    protected function casts(): array
    {
        return ['blocks' => 'array', 'number' => 'integer', 'restored_from' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las versiones de una página no se modifican: guarda una versión nueva.'));
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
