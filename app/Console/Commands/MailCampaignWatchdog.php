<?php

namespace App\Console\Commands;

use App\Jobs\ProcessMailCampaignJob;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use Illuminate\Console\Command;

class MailCampaignWatchdog extends Command
{
    protected $signature = 'mail:campaign-watchdog';
    protected $description = 'Recover stale mail campaign recipients and resume active campaigns';

    public function handle(): int
    {
        $stale = MailCampaignRecipient::where('status', 'sending')
            ->where('locked_at', '<', time() - 600)
            ->update([
                'status' => 'pending',
                'locked_at' => null,
                'locked_by' => null,
                'updated_at' => time(),
            ]);

        $campaigns = MailCampaign::whereIn('status', ['pending', 'sending'])->pluck('id');
        foreach ($campaigns as $campaignId) {
            ProcessMailCampaignJob::dispatch((int) $campaignId);
        }

        $this->info("Recovered {$stale} stale recipients; queued " . $campaigns->count() . ' campaigns.');
        return self::SUCCESS;
    }
}
