<?php

namespace App\Jobs;

use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ProcessMailCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    public function __construct(private int $campaignId)
    {
        $this->onQueue('send_email_mass_scheduler');
    }

    public function handle(): void
    {
        Cache::lock('mail:campaign:process:' . $this->campaignId, 55)->block(5, function () {
            $campaign = MailCampaign::find($this->campaignId);
            if (!$campaign || in_array($campaign->status, ['completed', 'cancelled', 'paused', 'paused_by_system'], true)) {
                return;
            }

            if ($campaign->status === 'pending') {
                $campaign->status = 'sending';
                $campaign->started_at = $campaign->started_at ?: time();
                $campaign->save();
            }

            MailCampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'sending')
                ->where('locked_at', '<', time() - 600)
                ->update([
                    'status' => 'pending',
                    'locked_at' => null,
                    'locked_by' => null,
                    'updated_at' => time(),
                ]);

            $recipients = MailCampaignRecipient::where('campaign_id', $campaign->id)
                ->where(function ($query) {
                    $query->where('status', 'pending')
                        ->orWhere(function ($query) {
                            $query->where('status', 'failed_retryable')
                                ->where(function ($query) {
                                    $query->whereNull('next_attempt_at')
                                        ->orWhere('next_attempt_at', '<=', time());
                                });
                        });
                })
                ->where('attempts', '<', $campaign->retry_limit)
                ->orderBy('id')
                ->limit((int) admin_setting('email_mass_dispatch_batch', 50))
                ->get();

            if ($recipients->isEmpty()) {
                $active = MailCampaignRecipient::where('campaign_id', $campaign->id)
                    ->whereIn('status', ['pending', 'sending', 'failed_retryable'])
                    ->count();
                if ($active === 0) {
                    $campaign->status = 'completed';
                    $campaign->finished_at = time();
                    $campaign->save();
                }
                return;
            }

            foreach ($recipients as $recipient) {
                $updated = MailCampaignRecipient::where('id', $recipient->id)
                    ->whereIn('status', ['pending', 'failed_retryable'])
                    ->update([
                        'status' => 'sending',
                        'locked_at' => time(),
                        'locked_by' => gethostname() ?: 'worker',
                        'updated_at' => time(),
                    ]);
                if ($updated) {
                    SendCampaignEmailJob::dispatch($recipient->id);
                }
            }

            self::dispatch($campaign->id)->delay(now()->addSeconds(30));
        });
    }
}
