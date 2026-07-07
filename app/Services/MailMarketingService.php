<?php

namespace App\Services;

use App\Jobs\ProcessMailCampaignJob;
use App\Models\MailCampaign;
use App\Models\MailCampaignRecipient;
use App\Models\MailTouchLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MailMarketingService
{
    public static function defaultRules(): array
    {
        return [
            'register_no_order_10m' => [
                'enabled' => false,
                'type' => 'register_no_order',
                'minutes' => 10,
                'template_name' => 'marketingRegisterNoOrder',
            ],
            'unpaid_order_10m' => [
                'enabled' => false,
                'type' => 'unpaid_order',
                'minutes' => 10,
                'template_name' => 'marketingUnpaidOrder',
            ],
            'expire_before_7d' => [
                'enabled' => false,
                'type' => 'expire_before_days',
                'days' => 7,
                'template_name' => 'marketingExpire7',
            ],
            'traffic_exhausted' => [
                'enabled' => false,
                'type' => 'traffic_exhausted',
                'template_name' => 'marketingTrafficExhausted',
            ],
            'expired_after_7d' => [
                'enabled' => false,
                'type' => 'expired_after_days',
                'days' => 7,
                'template_name' => 'marketingExpired7',
            ],
            'expired_after_15d' => [
                'enabled' => false,
                'type' => 'expired_after_days',
                'days' => 15,
                'template_name' => 'marketingExpired15',
            ],
        ];
    }

    public static function rulesForSettings(): string
    {
        $rules = admin_setting('email_marketing_rules_json');
        if (is_array($rules)) {
            return json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if (is_string($rules) && trim($rules) !== '') {
            return $rules;
        }
        return json_encode(self::defaultRules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function scan(?string $onlyRule = null, bool $dryRun = false): array
    {
        $rules = $this->getRules();
        $summary = [];
        foreach ($rules as $key => $rule) {
            if ($onlyRule && $key !== $onlyRule) {
                continue;
            }
            if (empty($rule['enabled'])) {
                $summary[$key] = ['enabled' => false, 'matched' => 0, 'queued' => 0, 'campaign_id' => null];
                continue;
            }
            $summary[$key] = $this->scanRule($key, $rule, $dryRun);
        }
        return $summary;
    }

    public function getRules(): array
    {
        $raw = admin_setting('email_marketing_rules_json');
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::sanitizeRules(array_replace_recursive(self::defaultRules(), $decoded));
            }
            Log::warning('Invalid email marketing rules json', ['error' => json_last_error_msg()]);
        }
        if (is_array($raw)) {
            return self::sanitizeRules(array_replace_recursive(self::defaultRules(), $raw));
        }
        return self::sanitizeRules(self::defaultRules());
    }

    private static function sanitizeRules(array $rules): array
    {
        foreach ($rules as &$rule) {
            unset($rule['title'], $rule['content']);
        }
        unset($rule);

        return $rules;
    }

    private function scanRule(string $key, array $rule, bool $dryRun): array
    {
        $query = $this->buildQuery($rule);
        if (!$query) {
            return ['enabled' => true, 'matched' => 0, 'queued' => 0, 'campaign_id' => null];
        }

        $matched = (clone $query)->count();
        if ($matched === 0 || $dryRun) {
            return ['enabled' => true, 'matched' => $matched, 'queued' => 0, 'campaign_id' => null];
        }

        $campaign = MailCampaign::create([
            'subject' => $this->templateSubject((string) ($rule['template_name'] ?? 'notify')),
            'content' => '',
            'scope_type' => 'marketing:' . $key,
            'scope_payload' => ['rule_key' => $key, 'rule' => $rule, 'template_name' => $rule['template_name'] ?? null],
            'rate_limit_per_hour' => (bool) admin_setting('email_mass_rate_limit_enable', 1)
                ? max(1, (int) admin_setting('email_mass_rate_limit_per_hour', 1800))
                : 1000000,
            'retry_limit' => max(1, (int) admin_setting('email_mass_retry_limit', 3)),
            'status' => 'pending',
            'created_at' => time(),
            'updated_at' => time(),
        ]);

        $queued = 0;
        $query->chunkById(1000, function ($users) use ($key, $rule, $campaign, &$queued) {
            $recipientRows = [];
            foreach ($users as $user) {
                $dedupeKey = $this->dedupeKey($key, $rule, $user);
                $touch = [
                    'rule_key' => $key,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'dedupe_hash' => sha1($dedupeKey),
                    'dedupe_key' => $dedupeKey,
                    'campaign_id' => $campaign->id,
                    'subject' => $campaign->subject,
                    'status' => 'queued',
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                $inserted = DB::table('v2_mail_touch_logs')->insertOrIgnore($touch);
                if (!$inserted) {
                    continue;
                }
                $recipientRows[] = [
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'vars' => json_encode($this->varsForUser($user), JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'attempts' => 0,
                    'created_at' => time(),
                    'updated_at' => time(),
                ];
                $queued++;
            }
            if ($recipientRows) {
                DB::table('v2_mail_campaign_recipients')->insertOrIgnore($recipientRows);
            }
        });

        $campaign->total_count = MailCampaignRecipient::where('campaign_id', $campaign->id)->count();
        $campaign->queued_count = $campaign->total_count;
        if ($campaign->total_count === 0) {
            $campaign->status = 'completed';
            $campaign->finished_at = time();
        }
        $campaign->save();

        if ($campaign->total_count > 0) {
            ProcessMailCampaignJob::dispatch($campaign->id);
        }

        return ['enabled' => true, 'matched' => $matched, 'queued' => $queued, 'campaign_id' => $campaign->id];
    }

    private function buildQuery(array $rule): ?Builder
    {
        $type = $rule['type'] ?? '';
        return match ($type) {
            'register_no_order' => $this->registerNoOrderQuery((int) ($rule['minutes'] ?? 10)),
            'unpaid_order' => $this->unpaidOrderUsersQuery((int) ($rule['minutes'] ?? 10)),
            'expire_before_days' => $this->expireBeforeQuery((int) ($rule['days'] ?? 7)),
            'expired_after_days' => $this->expiredAfterQuery((int) ($rule['days'] ?? 7)),
            'traffic_exhausted' => $this->trafficExhaustedQuery(),
            default => null,
        };
    }

    private function registerNoOrderQuery(int $minutes): Builder
    {
        return User::query()
            ->where('created_at', '>=', strtotime("-{$minutes} minute"))
            ->where('banned', false)
            ->whereNotNull('email')
            ->whereDoesntHave('orders', fn ($query) => $query->where('status', Order::STATUS_COMPLETED));
    }

    private function unpaidOrderUsersQuery(int $minutes): Builder
    {
        return User::query()
            ->where('banned', false)
            ->whereNotNull('email')
            ->whereHas('orders', function ($query) use ($minutes) {
                $query->where('created_at', '>=', strtotime("-{$minutes} minute"))
                    ->where('status', Order::STATUS_PENDING);
            });
    }

    private function expireBeforeQuery(int $days): Builder
    {
        $start = strtotime(date('Y-m-d 00:00:00', strtotime("+{$days} day")));
        $end = strtotime(date('Y-m-d 23:59:59', strtotime("+{$days} day")));
        return User::query()->whereBetween('expired_at', [$start, $end])->where('banned', false)->whereNotNull('email');
    }

    private function expiredAfterQuery(int $days): Builder
    {
        $start = strtotime(date('Y-m-d 00:00:00', strtotime("-{$days} day")));
        $end = strtotime(date('Y-m-d 23:59:59', strtotime("-{$days} day")));
        return User::query()->whereBetween('expired_at', [$start, $end])->where('banned', false)->whereNotNull('email');
    }

    private function trafficExhaustedQuery(): Builder
    {
        return User::query()
            ->where('banned', false)
            ->whereNotNull('email')
            ->where('transfer_enable', '>', 0)
            ->whereRaw('u + d >= transfer_enable');
    }

    private function dedupeKey(string $key, array $rule, User $user): string
    {
        $type = $rule['type'] ?? '';
        if ($type === 'traffic_exhausted') {
            return $key . ':cycle:' . ($user->last_reset_at ?: $user->expired_at ?: date('Ym'));
        }
        if (in_array($type, ['expire_before_days', 'expired_after_days'], true)) {
            return $key . ':expired_at:' . (int) $user->expired_at;
        }
        return $key . ':once';
    }

    private function varsForUser(User $user): array
    {
        return [
            'app.name' => admin_setting('app_name', 'XBoard'),
            'app.url' => admin_setting('app_url'),
            'now' => now()->format('Y-m-d H:i:s'),
            'user.id' => $user->id,
            'user.email' => $user->email,
            'user.uuid' => $user->uuid,
            'user.expired_at' => $user->expired_at ? date('Y-m-d H:i:s', $user->expired_at) : '',
            'user.transfer_enable' => (int) ($user->transfer_enable ?? 0),
            'user.transfer_used' => (int) (($user->u ?? 0) + ($user->d ?? 0)),
            'user.transfer_left' => (int) (($user->transfer_enable ?? 0) - (($user->u ?? 0) + ($user->d ?? 0))),
        ];
    }

    private function templateSubject(string $templateName): string
    {
        $appName = admin_setting('app_name', 'XBoard');
        return match ($templateName) {
            'marketingRegisterNoOrder' => "{$appName} - 未订阅提醒",
            'marketingUnpaidOrder' => "{$appName} - 订单未支付提醒",
            'marketingExpire7' => "{$appName} - 订阅将在7天后到期",
            'marketingTrafficExhausted' => "{$appName} - 流量用尽提醒",
            'marketingExpired7' => "{$appName} - 订阅已过期7天",
            'marketingExpired15' => "{$appName} - 订阅已过期15天",
            default => "{$appName} - 站点通知",
        };
    }
}
