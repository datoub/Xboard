<?php

namespace Tests\Unit;

use App\Services\Subscription\SubscriptionStateService;
use PHPUnit\Framework\TestCase;

class SubscriptionStateServiceTest extends TestCase
{
    public function test_state_precedence_and_active_state(): void
    {
        $service = new SubscriptionStateService();

        $this->assertSame(SubscriptionStateService::BANNED, $service->state($this->user([
            'banned' => 1,
            'plan_id' => null,
        ])));
        $this->assertSame(SubscriptionStateService::NO_PLAN, $service->state($this->user([
            'plan_id' => null,
        ])));
        $this->assertSame(SubscriptionStateService::EXPIRED, $service->state($this->user([
            'expired_at' => time() - 1,
        ])));
        $this->assertSame(SubscriptionStateService::TRAFFIC_EXHAUSTED, $service->state($this->user([
            'u' => 60,
            'd' => 40,
            'transfer_enable' => 100,
        ])));
        $this->assertSame(SubscriptionStateService::ACTIVE, $service->state($this->user([
            'expired_at' => null,
        ])));
        $this->assertSame(SubscriptionStateService::ACTIVE, $service->state($this->user()));

        $this->assertSame([
            'state' => SubscriptionStateService::ACTIVE,
            'available' => true,
        ], $service->describe($this->user()));
    }

    private function user(array $overrides = []): array
    {
        return array_merge([
            'banned' => 0,
            'plan_id' => 1,
            'expired_at' => time() + 86400,
            'u' => 10,
            'd' => 20,
            'transfer_enable' => 100,
        ], $overrides);
    }
}
