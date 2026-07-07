<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $table = 'v2_mail_templates';

    protected $fillable = ['name', 'subject', 'content'];

    /**
     * Template definitions: required/optional vars and default content.
     */
    public const TEMPLATES = [
        'verify' => [
            'label' => '邮箱验证码',
            'required_vars' => ['code'],
            'optional_vars' => ['name', 'url'],
        ],
        'notify' => [
            'label' => '站点通知',
            'required_vars' => ['content'],
            'optional_vars' => ['name', 'url'],
        ],
        'remindExpire' => [
            'label' => '到期提醒',
            'required_vars' => [],
            'optional_vars' => ['name', 'url'],
        ],
        'remindTraffic' => [
            'label' => '流量提醒',
            'required_vars' => [],
            'optional_vars' => ['name', 'url'],
        ],
        'mailLogin' => [
            'label' => '邮件登录',
            'required_vars' => ['link'],
            'optional_vars' => ['name', 'url'],
        ],
        'marketingRegisterNoOrder' => [
            'label' => '注册未订阅营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'now'],
        ],
        'marketingUnpaidOrder' => [
            'label' => '订单未支付营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'now'],
        ],
        'marketingExpire7' => [
            'label' => '7天后到期营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'user.expired_at', 'now'],
        ],
        'marketingTrafficExhausted' => [
            'label' => '流量用尽营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'user.transfer_used', 'user.transfer_enable', 'now'],
        ],
        'marketingExpired7' => [
            'label' => '已过期7天营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'user.expired_at', 'now'],
        ],
        'marketingExpired15' => [
            'label' => '已过期15天营销',
            'required_vars' => [],
            'optional_vars' => ['name', 'url', 'user.email', 'user.expired_at', 'now'],
        ],
    ];

    /**
     * Get template metadata (vars, label) for a given template name.
     */
    public static function getMeta(string $name): ?array
    {
        return self::TEMPLATES[$name] ?? null;
    }

    /**
     * Get all template names.
     */
    public static function getNames(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * Validate that required placeholders are present in the content.
     */
    public static function validateContent(string $name, string $content): array
    {
        $meta = self::getMeta($name);
        if (!$meta) {
            return ["Unknown template: {$name}"];
        }

        $errors = [];
        foreach ($meta['required_vars'] as $var) {
            if (strpos($content, '{{' . $var . '}}') === false) {
                $errors[] = "缺少必要占位符: {{{$var}}}";
            }
        }
        return $errors;
    }
}
