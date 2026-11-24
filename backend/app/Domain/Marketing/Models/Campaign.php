<?php

namespace App\Domain\Marketing\Models;

use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use Auditable;

    public const STATUSES = ['draft' => 'Borrador', 'scheduled' => 'Programada', 'sending' => 'Enviando', 'sent' => 'Enviada', 'cancelled' => 'Cancelada'];

    public const AUDIENCES = [
        'all' => 'Todos los suscritos',
        'buyers' => 'Suscritos que ya compraron',
        'non_buyers' => 'Suscritos que aún no compran',
    ];

    protected $fillable = [
        'name', 'subject', 'preheader', 'heading', 'body', 'button_label', 'button_url', 'audience', 'scheduled_at', 'status',
        'recipients_count', 'sent_count', 'failed_count', 'skipped_count', 'clicks_count', 'started_at', 'finished_at', 'created_by',
    ];

    protected $attributes = ['status' => 'draft', 'audience' => 'all'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }
}
