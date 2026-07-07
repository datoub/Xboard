<?php

namespace App\Services;

use App\Models\MailCampaignRecipient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MailRateLimiter
{
    private const KEY = 'mail:campaign:rate:events';

    public static function acquire(int $limitPerHour): array
    {
        $limitPerHour = max(1, $limitPerHour);
        $now = time();
        $windowStart = $now - 3600;

        try {
            return Cache::lock('mail:campaign:rate:lock', 5)->block(5, function () use ($limitPerHour, $now, $windowStart) {
                Redis::zremrangebyscore(self::KEY, 0, $windowStart);
                $used = (int) Redis::zcard(self::KEY);
                if ($used >= $limitPerHour) {
                    $oldest = Redis::zrange(self::KEY, 0, 0, ['withscores' => true]);
                    $oldestScore = is_array($oldest) && count($oldest) ? (int) reset($oldest) : time();
                    return [false, max(5, ($oldestScore + 3600) - time())];
                }
                Redis::zadd(self::KEY, $now, $now . ':' . bin2hex(random_bytes(8)));
                Redis::expire(self::KEY, 7200);
                return [true, 0];
            });
        } catch (\Throwable $e) {
            Log::warning('Mail campaign Redis rate limiter fallback', ['error' => $e->getMessage()]);
            $used = MailCampaignRecipient::where('status', 'sent')->where('sent_at', '>=', $windowStart)->count();
            return $used < $limitPerHour ? [true, 0] : [false, 60];
        }
    }
}
