<?php

namespace App\Http\Requests\Admin;

use App\Services\Plugin\HookManager;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdate extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'id' => 'required|integer',
            'email' => 'email:strict',
            'password' => 'nullable|min:8',
            'transfer_enable' => 'numeric',
            'expired_at' => 'nullable|integer',
            'banned' => 'bool',
            'plan_id' => 'nullable|integer',
            'commission_rate' => 'nullable|integer|min:0|max:100',
            'discount' => 'nullable|integer|min:0|max:100',
            'is_admin' => 'boolean',
            'is_staff' => 'boolean',
            'u' => 'integer',
            'd' => 'integer',
            'balance' => 'numeric',
            'commission_type' => 'integer',
            'commission_balance' => 'numeric',
            'remarks' => 'nullable',
            'speed_limit' => 'nullable|integer',
            'device_limit' => 'nullable|integer',
            'dynamic_speed_limit' => 'nullable|array',
            'dynamic_speed_limit.enabled' => 'boolean',
            'dynamic_speed_limit.threshold_mbps' => 'nullable|integer|min:0',
            'dynamic_speed_limit.trigger_seconds' => 'nullable|integer|min:0',
            'dynamic_speed_limit.limit_mbps' => 'nullable|integer|min:0',
            'dynamic_speed_limit.recovery_seconds' => 'nullable|integer|min:0',
            'dynamic_speed_limit.time_ranges' => 'nullable|array',
            'dynamic_speed_limit.time_ranges.*.start' => 'required_with:dynamic_speed_limit.time_ranges|string|date_format:H:i',
            'dynamic_speed_limit.time_ranges.*.end' => 'required_with:dynamic_speed_limit.time_ranges|string|date_format:H:i'
        ];

        return HookManager::filter('admin.user.update.rules', $rules, $this);
    }

    public function messages()
    {
        $messages = [
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'transfer_enable.numeric' => '流量格式不正确',
            'expired_at.integer' => '到期时间格式不正确',
            'banned.in' => '是否封禁格式不正确',
            'is_admin.required' => '是否管理员不能为空',
            'is_admin.in' => '是否管理员格式不正确',
            'is_staff.required' => '是否员工不能为空',
            'is_staff.in' => '是否员工格式不正确',
            'plan_id.integer' => '订阅计划格式不正确',
            'commission_rate.integer' => '推荐返利比例格式不正确',
            'commission_rate.nullable' => '推荐返利比例格式不正确',
            'commission_rate.min' => '推荐返利比例最小为0',
            'commission_rate.max' => '推荐返利比例最大为100',
            'discount.integer' => '专属折扣比例格式不正确',
            'discount.nullable' => '专属折扣比例格式不正确',
            'discount.min' => '专属折扣比例最小为0',
            'discount.max' => '专属折扣比例最大为100',
            'u.integer' => '上行流量格式不正确',
            'd.integer' => '下行流量格式不正确',
            'balance.integer' => '余额格式不正确',
            'commission_balance.integer' => '佣金格式不正确',
            'password.min' => '密码长度最小8位',
            'speed_limit.integer' => '限速格式不正确',
            'device_limit.integer' => '设备数量格式不正确',
            'dynamic_speed_limit.array' => '动态限速策略格式不正确',
            'dynamic_speed_limit.threshold_mbps.integer' => '动态限速触发带宽格式不正确',
            'dynamic_speed_limit.trigger_seconds.integer' => '动态限速触发时长格式不正确',
            'dynamic_speed_limit.limit_mbps.integer' => '动态限速限制带宽格式不正确',
            'dynamic_speed_limit.recovery_seconds.integer' => '动态限速恢复时长格式不正确',
            'dynamic_speed_limit.time_ranges.*.start.date_format' => '动态限速开始时间格式应为HH:mm',
            'dynamic_speed_limit.time_ranges.*.end.date_format' => '动态限速结束时间格式应为HH:mm'
        ];

        return HookManager::filter('admin.user.update.messages', $messages, $this);
    }
}
