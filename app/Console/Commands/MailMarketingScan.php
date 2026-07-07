<?php

namespace App\Console\Commands;

use App\Services\MailMarketingService;
use Illuminate\Console\Command;

class MailMarketingScan extends Command
{
    protected $signature = 'mail:marketing-scan {--rule=} {--dry-run} {--force}';
    protected $description = 'Scan marketing mail rules and create rate-limited campaigns';

    public function handle(MailMarketingService $service): int
    {
        if (!admin_setting('email_marketing_enable', false) && !$this->option('force')) {
            $this->warn('营销邮件功能未启用');
            return self::SUCCESS;
        }

        $summary = $service->scan(
            $this->option('rule') ?: null,
            (bool) $this->option('dry-run')
        );

        $this->table(['规则', '启用', '命中', '入队', '任务ID'], collect($summary)->map(function ($row, $key) {
            return [
                $key,
                empty($row['enabled']) ? '否' : '是',
                $row['matched'] ?? 0,
                $row['queued'] ?? 0,
                $row['campaign_id'] ?? '-',
            ];
        })->values()->toArray());

        return self::SUCCESS;
    }
}
