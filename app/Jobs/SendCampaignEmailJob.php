<?php

namespace App\Jobs;

use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailTemplate;
use App\Services\MailRateLimiter;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 30;

    public function __construct(private int $recipientId)
    {
        $this->onQueue('send_email_mass');
    }

    public function handle(): void
    {
        $recipient = MailCampaignRecipient::find($this->recipientId);
        if (!$recipient || $recipient->status === 'sent') {
            return;
        }

        $campaign = MailCampaign::find($recipient->campaign_id);
        if (!$campaign || $campaign->status !== 'sending') {
            $this->release(30);
            return;
        }

        [$allowed, $waitSeconds] = MailRateLimiter::acquire((int) $campaign->rate_limit_per_hour);
        if (!$allowed) {
            $recipient->status = 'pending';
            $recipient->locked_at = null;
            $recipient->locked_by = null;
            $recipient->save();
            $this->release($waitSeconds);
            ProcessMailCampaignJob::dispatch($campaign->id)->delay(now()->addSeconds($waitSeconds));
            return;
        }

        $recipient->attempts++;
        $recipient->locked_at = time();
        $recipient->save();

        $payload = is_array($campaign->scope_payload) ? $campaign->scope_payload : [];
        $templateName = $payload['template_name'] ?? 'notify';
        if ($templateName !== 'notify' && !MailTemplate::getMeta($templateName)) {
            $templateName = 'notify';
        }

        $vars = is_array($recipient->vars) ? $recipient->vars : (json_decode((string) $recipient->vars, true) ?: []);

        $log = MailService::sendEmail([
            'email' => $recipient->email,
            'subject' => $campaign->subject,
            'template_name' => $templateName,
            'template_value' => [
                'name' => admin_setting('app_name', 'XBoard'),
                'url' => admin_setting('app_url'),
                'content' => $campaign->content,
                'vars' => $vars,
                'content_mode' => 'text',
            ],
        ]);

        if (empty($log['error'])) {
            $recipient->status = 'sent';
            $recipient->sent_at = time();
            $recipient->last_error = null;
            $recipient->locked_at = null;
            $recipient->locked_by = null;
            $recipient->save();
        } else {
            $recipient->last_error = $log['error'];
            $recipient->locked_at = null;
            $recipient->locked_by = null;
            $recipient->next_attempt_at = time() + $this->retryDelay($recipient->attempts);
            $recipient->status = $this->isFatalError($log['error']) || $recipient->attempts >= $campaign->retry_limit
                ? 'failed_final'
                : 'failed_retryable';
            $recipient->save();

            if ($this->isFatalError($log['error'])) {
                $campaign->status = 'paused_by_system';
                $campaign->last_error = $log['error'];
                $campaign->save();
            }
        }

        $this->refreshCampaignStats($campaign);
        ProcessMailCampaignJob::dispatch($campaign->id)->delay(now()->addSeconds(5));
    }

    private function retryDelay(int $attempts): int
    {
        return match ($attempts) {
            1 => 300,
            2 => 900,
            default => 1800,
        };
    }

    private function isFatalError(string $error): bool
    {
        return preg_match('/auth|authentication|password|credential|535|550 5\\.7|unauthor/i', $error) === 1;
    }

    private function refreshCampaignStats(MailCampaign $campaign): void
    {
        $campaign->sent_count = MailCampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'sent')->count();
        $campaign->failed_count = MailCampaignRecipient::where('campaign_id', $campaign->id)->where('status', 'failed_final')->count();
        $campaign->queued_count = MailCampaignRecipient::where('campaign_id', $campaign->id)->whereIn('status', ['pending', 'sending', 'failed_retryable'])->count();
        $campaign->save();
    }
}
