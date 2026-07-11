<?php

namespace Tests\Unit;

use App\Services\Subscription\MihomoDnsHardeningService;
use PHPUnit\Framework\TestCase;

class MihomoDnsHardeningServiceTest extends TestCase
{
    public function test_non_canary_output_is_unchanged(): void
    {
        $service = new MihomoDnsHardeningService($this->settings());
        $config = $this->config();

        $this->assertSame($config, $service->apply($config, ['id' => 43]));
    }

    public function test_canary_gets_node_dns_and_fake_ip_direct_rule_is_removed(): void
    {
        $service = new MihomoDnsHardeningService($this->settings());
        $config = $service->apply($this->config(), ['id' => 42]);

        $this->assertSame(
            ['1.1.1.1', '8.8.8.8'],
            $config['dns']['proxy-server-nameserver']
        );
        $this->assertSame(
            ['1.1.1.1', '8.8.8.8'],
            $config['dns']['proxy-server-nameserver-policy']['+.nodes.example']
        );
        $this->assertSame(
            ['https://dns.example/dns-query'],
            $config['dns']['proxy-server-nameserver-policy']['+.example.com']
        );
        $this->assertNotContains(
            'IP-CIDR,198.18.0.0/16,DIRECT,no-resolve',
            $config['rules']
        );
        $this->assertContains('IP-CIDR,10.0.0.0/8,DIRECT,no-resolve', $config['rules']);
    }

    public function test_mihomo_user_agent_override_is_canary_only(): void
    {
        $service = new MihomoDnsHardeningService($this->settings());

        $this->assertTrue($service->supportsUserAgent(
            ['id' => 42],
            'ClashMeta/1.19.27; mihomo/1.19.27'
        ));
        $this->assertFalse($service->supportsUserAgent(['id' => 43], 'mihomo/1.19.27'));
        $this->assertFalse($service->supportsUserAgent(['id' => 42], 'Shadowrocket/3280'));
    }

    public function test_global_switch_enables_users_outside_canary(): void
    {
        $service = new MihomoDnsHardeningService($this->settings([
            'subscription_mihomo_dns_hardening_enable' => true,
            'subscription_mihomo_dns_canary_user_ids' => [],
        ]));

        $config = $service->apply($this->config(), ['id' => 99]);

        $this->assertArrayHasKey('proxy-server-nameserver', $config['dns']);
    }

    private function settings(array $overrides = []): array
    {
        return array_merge([
            'subscription_mihomo_dns_hardening_enable' => false,
            'subscription_mihomo_dns_canary_user_ids' => [42],
            'subscription_mihomo_node_domains' => ['+.nodes.example'],
            'subscription_mihomo_node_resolvers' => ['1.1.1.1', '8.8.8.8'],
        ], $overrides);
    }

    private function config(): array
    {
        return [
            'dns' => [
                'enable' => true,
                'proxy-server-nameserver-policy' => [
                    '+.example.com' => ['https://dns.example/dns-query'],
                ],
            ],
            'rules' => [
                'IP-CIDR,10.0.0.0/8,DIRECT,no-resolve',
                'IP-CIDR,198.18.0.0/16,DIRECT,no-resolve',
                'MATCH,Proxy',
            ],
        ];
    }
}
