<?php

namespace App\Services\Subscription;

class SubscriptionStateService
{
    public const ACTIVE = 'ACTIVE';
    public const NO_PLAN = 'NO_PLAN';
    public const EXPIRED = 'EXPIRED';
    public const TRAFFIC_EXHAUSTED = 'TRAFFIC_EXHAUSTED';
    public const BANNED = 'BANNED';

    public function describe(mixed $user): array
    {
        $state = $this->state($user);

        return [
            'state' => $state,
            'available' => $state === self::ACTIVE,
        ];
    }

    public function state(mixed $user): string
    {
        if ((bool) data_get($user, 'banned', false)) {
            return self::BANNED;
        }

        if ((int) data_get($user, 'plan_id', 0) <= 0) {
            return self::NO_PLAN;
        }

        $expiredAt = data_get($user, 'expired_at');
        if ($expiredAt !== null && (int) $expiredAt <= time()) {
            return self::EXPIRED;
        }

        $total = (int) data_get($user, 'transfer_enable', 0);
        $used = (int) data_get($user, 'u', 0) + (int) data_get($user, 'd', 0);
        if ($total <= 0 || $used >= $total) {
            return self::TRAFFIC_EXHAUSTED;
        }

        return self::ACTIVE;
    }
}
