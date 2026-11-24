<?php

namespace App\Domain\Marketing\Jobs;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignRecipient;
use App\Domain\Marketing\Services\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Un lote de destinatarios de una campaña. Si la campaña se canceló, no envía nada. */
class SendCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $campaignId, public array $recipientIds) {}

    public function handle(CampaignService $service): void
    {
        foreach ($this->recipientIds as $id) {
            $campaign = Campaign::query()->find($this->campaignId);
            if (! $campaign || $campaign->status !== 'sending') {
                return;
            }
            $recipient = CampaignRecipient::query()->with('campaign')->where('campaign_id', $campaign->id)->where('status', 'pending')->find($id);
            if ($recipient) {
                $service->deliver($campaign, $recipient);
            }
        }
        if ($campaign = Campaign::query()->find($this->campaignId)) {
            $service->finishIfDone($campaign);
        }
    }
}
