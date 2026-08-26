<?php

namespace App\Services;

use App\Models\Setting;

class SettingService
{
    public function timezoneList()
    {
        $zones_array = array();
        $timestamp = time();
        foreach (timezone_identifiers_list() as $key => $zone) {
            date_default_timezone_set($zone);
            $zones_array[$key]['zone'] = $zone;
            $zones_array[$key]['offset'] = (int) ((int) date('O', $timestamp)) / 100;
            $zones_array[$key]['diff_from_GMT'] = date('P', $timestamp);
            $zones_array[$key]['time'] = date('h:i:s A');
        }
        return $zones_array;
    }

    public function getAiSettings()
    {
        $aiSettings = json_decode($this->getSettings('ai_settings', true), true);
        $geminiApikey = json_decode($this->getSettings('gemini_api_key', true), true);
        $openRouterApiKey = json_decode($this->getSettings('openrouter_api_key', true), true);

        return [
            'ai_method' => $aiSettings['ai_method'] ?? '',
            'gemini_api_key' => $geminiApikey['gemini_api_key'] ?? '',
            'openrouter_api_key' => $openRouterApiKey['openrouter_api_key'] ?? '',
        ];
    }

    public function getSettings($type = 'system_settings', $isJson = false)
    {

        static $cache = [];

        $key = $isJson ? 'json' : 'raw';

        if (isset($cache[$type][$key])) {
            return $cache[$type][$key];
        }

        $settingValue = Setting::where('variable', $type)->value('value');
        if (is_null($settingValue)) {
            return null;
        }

        $settingsArray = json_decode($settingValue, true) ?? [];

        // Attach currency only for system_settings
        if ($type == 'system_settings') {
            $settingsArray['currency_setting'] = app(CurrencyService::class)->getDefaultCurrency();
            $settingsArray['ai_setting'] = app(SettingService::class)->getAiSettings();
        }

        $result = $isJson
            ? json_encode($settingsArray)
            : htmlspecialchars_decode(json_encode($settingsArray));

        $cache[$type]['raw'] = htmlspecialchars_decode(json_encode($settingsArray));
        $cache[$type]['json'] = json_encode($settingsArray);

        return $result;
    }
    
}