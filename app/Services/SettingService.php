<?php


namespace App\Services;


use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Class SettingsService
 * @package App\Services
 */
class SettingService
{
    const SETTINGS = 'settings';
    // const SETTINGS_SLOTS = 'setting_slots';

    public static function getSettings() {

        return Cache::rememberForever(SettingService::SETTINGS, function () {
            return Setting::query()
                ->orderBy('created_at')
                ->get();
        });

    }

    public static function getSetting($name)
    {
        return self::getSettings()->where('name', $name)->first();
    }

    public static function getNumberOfDaysToExpire() {
        $setting = self::getSetting(Setting::NUMBER_OF_DAYS_TO_EXPIRE);
        return intval($setting->value ?? 5);
    }
    public static function getOrderRefundTime() {
        $setting = self::getSetting(Setting::ORDER_REFUND_TIME);
        return intval($setting->value ?? 5);
    }
    public static function getOrderRefundedByHours() {
        $setting = self::getSetting(Setting::ORDER_REFUNDED_BY_HOURS);
        return $setting->value == 'true' ? true : false;
    }

    public function refreshSettings()
    {
        Cache::forget(SettingService::SETTINGS);
    }
}
