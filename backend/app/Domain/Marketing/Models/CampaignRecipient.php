<?php

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = ['campaign_id', 'subscriber_id', 'email', 'status', 'attempts', 'error', 'sent_at', 'clicked_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'clicked_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class);
    }
}
