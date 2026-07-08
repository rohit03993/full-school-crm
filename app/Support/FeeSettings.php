<?php

namespace App\Support;

use App\Models\Setting;

class FeeSettings
{
    public const KEY_ONLINE_ALLOWANCE_GST_ENABLED = 'fees.online_allowance_gst_enabled';

    public const KEY_GST_PENALTY_PERCENTAGE = 'fees.gst_penalty_percentage';

    public const KEY_LATE_FEE_ENABLED = 'fees.late_fee_enabled';

    public static function onlineAllowanceGstEnabled(): bool
    {
        $value = Setting::getValue(self::KEY_ONLINE_ALLOWANCE_GST_ENABLED, config('fees.online_allowance_gst.enabled', false));

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function gstPenaltyPercentage(): float
    {
        $value = Setting::getValue(self::KEY_GST_PENALTY_PERCENTAGE, config('fees.online_allowance_gst.percentage', 18));

        return max(0, (float) $value);
    }

    public static function lateFeeEnabled(): bool
    {
        $value = Setting::getValue(self::KEY_LATE_FEE_ENABLED, config('fees.late_fee.enabled', true));

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function lateFeeGraceDays(): int
    {
        return max(0, (int) config('fees.late_fee.grace_days', 7));
    }

    public static function lateFeeDailyRate(): float
    {
        return max(0, (float) config('fees.late_fee.daily_rate', 0.0015));
    }

    /**
     * @return array<string, mixed>
     */
    public static function formDefaults(): array
    {
        return [
            'online_allowance_gst_enabled' => self::onlineAllowanceGstEnabled(),
            'gst_penalty_percentage' => self::gstPenaltyPercentage(),
            'late_fee_enabled' => self::lateFeeEnabled(),
            'late_fee_grace_days' => self::lateFeeGraceDays(),
            'late_fee_daily_rate' => self::lateFeeDailyRate(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function saveFormData(array $data): void
    {
        Setting::setValue(
            self::KEY_ONLINE_ALLOWANCE_GST_ENABLED,
            ($data['online_allowance_gst_enabled'] ?? false) ? '1' : '0',
            'fees',
        );
        Setting::setValue(
            self::KEY_GST_PENALTY_PERCENTAGE,
            (string) max(0, (float) ($data['gst_penalty_percentage'] ?? 18)),
            'fees',
        );
        Setting::setValue(
            self::KEY_LATE_FEE_ENABLED,
            ($data['late_fee_enabled'] ?? false) ? '1' : '0',
            'fees',
        );
        Setting::flushValueCache();
    }
}
