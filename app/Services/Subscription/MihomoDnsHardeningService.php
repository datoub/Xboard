<?php

namespace App\Services\Subscription;

class MihomoDnsHardeningService
{
    private const FAKE_IP_DIRECT_RULE = '/^IP-CIDR,198\.18\.0\.0\/16,DIRECT(?:,no-resolve)?$/i';

    public function __construct(private readonly ?array $settings = null)
    {
    }

    public function isEnabledFor(mixed $user): bool
    {
        if ((bool) $this->setting('subscription_mihomo_dns_hardening_enable', false)) {
            return true;
        }

        return in_array(
            (int) data_get($user, 'id', 0),
            $this->normalizeIds($this->setting('subscription_mihomo_dns_canary_user_ids', [])),
            true
        );
    }

    public function supportsUserAgent(mixed $user, string $userAgent): bool
    {
        return $this->isEnabledFor($user)
            && preg_match('/(?:^|[\s;\/])mihomo(?:$|[\s;\/])/i', $userAgent) === 1;
    }

    public function apply(array $config, mixed $user): array
    {
        if (!$this->isEnabledFor($user)) {
            return $config;
        }

        $resolvers = $this->normalizeStrings(
            $this->setting('subscription_mihomo_node_resolvers', [])
        );
        $domains = $this->normalizeStrings(
            $this->setting('subscription_mihomo_node_domains', [])
        );

        if ($resolvers === [] || $domains === []) {
            return $config;
        }

        $dns = is_array($config['dns'] ?? null) ? $config['dns'] : [];
        $dns['proxy-server-nameserver'] = $resolvers;

        $policy = is_array($dns['proxy-server-nameserver-policy'] ?? null)
            ? $dns['proxy-server-nameserver-policy']
            : [];
        foreach ($domains as $domain) {
            $policy[$domain] = $resolvers;
        }
        $dns['proxy-server-nameserver-policy'] = $policy;
        $config['dns'] = $dns;

        if (is_array($config['rules'] ?? null)) {
            $config['rules'] = array_values(array_filter(
                $config['rules'],
                static fn($rule): bool => !is_string($rule)
                    || preg_match(self::FAKE_IP_DIRECT_RULE, trim($rule)) !== 1
            ));
        }

        return $config;
    }

    private function normalizeIds(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($id) => (int) $id,
            $this->normalizeValues($value)
        ))));
    }

    private function normalizeStrings(mixed $value): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn($item) => trim((string) $item),
            $this->normalizeValues($value)
        ), static fn(string $item): bool => $item !== '')));
    }

    private function normalizeValues(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[,|\s]+/', $value) ?: [];
        }

        return is_array($value) ? $value : [];
    }

    private function setting(string $key, mixed $default): mixed
    {
        if ($this->settings !== null) {
            return $this->settings[$key] ?? $default;
        }

        return admin_setting($key, $default);
    }
}
