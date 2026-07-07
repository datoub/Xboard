<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailTemplate;
use App\Services\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MailTemplateController extends Controller
{
    public function list()
    {
        $dbTemplates = MailTemplate::all()->keyBy('name');

        $result = [];
        foreach (MailTemplate::TEMPLATES as $name => $meta) {
            $db = $dbTemplates->get($name);
            $result[] = [
                'name' => $name,
                'label' => $meta['label'],
                'customized' => $db !== null,
                'subject' => $db?->subject,
                'updated_at' => $db?->updated_at?->timestamp,
            ];
        }

        return $this->success($result);
    }

    public function get(Request $request)
    {
        $name = $request->input('name');
        $meta = MailTemplate::getMeta($name);
        if (!$meta) {
            return $this->fail([404, '模板不存在']);
        }

        $db = MailTemplate::where('name', $name)->first();

        return $this->success([
            'name' => $name,
            'label' => $meta['label'],
            'required_vars' => $meta['required_vars'],
            'optional_vars' => $meta['optional_vars'],
            'customized' => $db !== null,
            'subject' => $db?->subject ?? $this->getDefaultSubject($name),
            'content' => $db?->content ?? $this->getDefaultContent($name),
        ]);
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'name' => 'required|string',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $meta = MailTemplate::getMeta($params['name']);
        if (!$meta) {
            return $this->fail([404, '模板不存在']);
        }

        $errors = MailTemplate::validateContent($params['name'], $params['content']);
        if (!empty($errors)) {
            return $this->fail([422, implode('; ', $errors)]);
        }

        MailTemplate::updateOrCreate(
            ['name' => $params['name']],
            ['subject' => $params['subject'], 'content' => $params['content']]
        );
        Cache::forget("mail_template:{$params['name']}");

        return $this->success(true);
    }

    public function reset(Request $request)
    {
        $name = $request->input('name');
        $meta = MailTemplate::getMeta($name);
        if (!$meta) {
            return $this->fail([404, '模板不存在']);
        }

        MailTemplate::where('name', $name)->delete();
        Cache::forget("mail_template:{$name}");
        return $this->success(true);
    }

    public function test(Request $request)
    {
        $name = $request->input('name');
        $meta = MailTemplate::getMeta($name);
        if (!$meta) {
            return $this->fail([404, '模板不存在']);
        }

        $email = $request->input('email', $request->user()->email);
        $testVars = $this->getTestVars($name);

        try {
            $log = MailService::sendEmail([
                'email' => $email,
                'subject' => $this->getTestSubject($name),
                'template_name' => $name,
                'template_value' => $testVars,
            ]);

            if ($log['error']) {
                return $this->fail([500, '发送失败: ' . $log['error']]);
            }
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->fail([500, '发送失败: ' . $e->getMessage()]);
        }
    }

    private function getTestSubject(string $name): string
    {
        $appName = admin_setting('app_name', 'XBoard');
        return match ($name) {
            'verify' => "{$appName} - 验证码测试",
            'notify' => "{$appName} - 通知测试",
            'remindExpire' => "{$appName} - 到期提醒测试",
            'remindTraffic' => "{$appName} - 流量提醒测试",
            'mailLogin' => "{$appName} - 登录链接测试",
            'marketingRegisterNoOrder' => "{$appName} - 注册未订阅营销测试",
            'marketingUnpaidOrder' => "{$appName} - 订单未支付营销测试",
            'marketingExpire7' => "{$appName} - 7天后到期营销测试",
            'marketingTrafficExhausted' => "{$appName} - 流量用尽营销测试",
            'marketingExpired7' => "{$appName} - 已过期7天营销测试",
            'marketingExpired15' => "{$appName} - 已过期15天营销测试",
            default => "{$appName} - 邮件测试",
        };
    }

    private function getTestVars(string $name): array
    {
        $appName = admin_setting('app_name', 'XBoard');
        $appUrl = admin_setting('app_url', 'https://example.com');

        return match ($name) {
            'verify' => [
                'name' => $appName,
                'code' => '123456',
                'url' => $appUrl,
            ],
            'notify' => [
                'name' => $appName,
                'content' => '这是一封测试通知邮件。',
                'url' => $appUrl,
            ],
            'remindExpire' => [
                'name' => $appName,
                'url' => $appUrl,
            ],
            'remindTraffic' => [
                'name' => $appName,
                'url' => $appUrl,
            ],
            'mailLogin' => [
                'name' => $appName,
                'link' => $appUrl . '/login?token=test-token',
                'url' => $appUrl,
            ],
            'marketingRegisterNoOrder',
            'marketingUnpaidOrder',
            'marketingExpire7',
            'marketingTrafficExhausted',
            'marketingExpired7',
            'marketingExpired15' => [
                'name' => $appName,
                'url' => $appUrl,
                'now' => now()->format('Y-m-d H:i:s'),
                'user.email' => 'user@example.com',
                'user.expired_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'user.transfer_enable' => 107374182400,
                'user.transfer_used' => 107374182400,
                'user.transfer_left' => 0,
            ],
            default => ['name' => $appName, 'url' => $appUrl],
        };
    }

    private function getDefaultSubject(string $name): string
    {
        $appName = admin_setting('app_name', 'XBoard');
        return match ($name) {
            'verify' => "{$appName} - 邮箱验证码",
            'notify' => "{$appName} - 站点通知",
            'remindExpire' => "{$appName} - 服务即将到期",
            'remindTraffic' => "{$appName} - 流量使用提醒",
            'mailLogin' => "{$appName} - 邮件登录",
            'marketingRegisterNoOrder' => "{$appName} - 未订阅提醒",
            'marketingUnpaidOrder' => "{$appName} - 订单未支付提醒",
            'marketingExpire7' => "{$appName} - 订阅将在7天后到期",
            'marketingTrafficExhausted' => "{$appName} - 流量用尽提醒",
            'marketingExpired7' => "{$appName} - 订阅已过期7天",
            'marketingExpired15' => "{$appName} - 订阅已过期15天",
            default => "{$appName}",
        };
    }

    private function getDefaultContent(string $name): string
    {
        $theme = 'default';
        $viewName = "mail.{$theme}.{$name}";

        try {
            $viewPath = resource_path("views/mail/{$theme}/{$name}.blade.php");
            if (file_exists($viewPath)) {
                $blade = file_get_contents($viewPath);
                return self::bladeToPlaceholder($blade);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return self::hardcodedDefault($name);
    }

    /**
     * Convert Blade syntax to {{placeholder}} syntax for editing.
     */
    private static function bladeToPlaceholder(string $blade): string
    {
        // {{$var}} → {{var}}
        $result = preg_replace('/\{\{\s*\$([a-zA-Z_]+)\s*\}\}/', '{{$1}}', $blade);
        // {!! nl2br($var) !!} → {{var}}
        $result = preg_replace('/\{!!\s*nl2br\(\$([a-zA-Z_]+)\)\s*!!\}/', '{{$1}}', $result);
        // {!! $var !!} → {{var}}
        $result = preg_replace('/\{!!\s*\$([a-zA-Z_]+)\s*!!\}/', '{{$1}}', $result);
        return $result;
    }

    private static function hardcodedDefault(string $name): string
    {
        $layout = fn($title, $body) => <<<HTML
<div style="background: #eee">
    <table width="600" border="0" align="center" cellpadding="0" cellspacing="0">
        <tbody>
        <tr>
            <td>
                <div style="background:#fff">
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <thead>
                        <tr>
                            <td valign="middle" style="padding-left:30px;background-color:#415A94;color:#fff;padding:20px 40px;font-size: 21px;">{{name}}</td>
                        </tr>
                        </thead>
                        <tbody>
                        <tr style="padding:40px 40px 0 40px;display:table-cell">
                            <td style="font-size:24px;line-height:1.5;color:#000;margin-top:40px">{$title}</td>
                        </tr>
                        <tr>
                            <td style="font-size:14px;color:#333;padding:24px 40px 0 40px">
                                尊敬的用户您好！<br /><br />{$body}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div>
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tbody>
                        <tr>
                            <td style="padding:20px 40px;font-size:12px;color:#999;line-height:20px;background:#f7f7f7"><a href="{{url}}" style="font-size:14px;color:#929292">返回{{name}}</a></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
        </tbody>
    </table>
</div>
HTML;

        return match ($name) {
            'verify' => $layout('邮箱验证码', '您的验证码是：{{code}}，请在 5 分钟内进行验证。如果该验证码不为您本人申请，请无视。'),
            'notify' => $layout('网站通知', '{{content}}'),
            'remindExpire' => $layout('服务到期提醒', '您的服务即将在24小时内到期，如需继续使用请及时续费。'),
            'remindTraffic' => $layout('流量使用提醒', '您的流量使用已达到80%，请注意流量使用情况。'),
            'mailLogin' => $layout('登入到{{name}}', '您正在登入到{{name}}, 请在 5 分钟内点击下方链接进行登入。如果您未授权该登入请求，请无视。<a href="{{link}}">{{link}}</a>'),
            'marketingRegisterNoOrder' => $layout('未订阅提醒', '您的账户还未订阅套餐，请尽快订阅后使用哦。'),
            'marketingUnpaidOrder' => $layout('订单未支付提醒', '您的订阅订单还未支付，请尽快完成支付后使用哦。'),
            'marketingExpire7' => $layout('订阅将在7天后到期', '您的订阅将在7天后到期，请尽快续费，以免影响使用。'),
            'marketingTrafficExhausted' => $layout('流量用尽提醒', '您的流量已用尽，请购买重置流量包，以免影响使用。'),
            'marketingExpired7' => $layout('订阅已过期7天', '您的订阅已过期7天，请尽快续费，以免影响使用。'),
            'marketingExpired15' => $layout('订阅已过期15天', '您的订阅已过期15天，请尽快续费，以免影响使用。'),
            default => $layout('通知', '{{content}}'),
        };
    }
}
