<?php

namespace App\Services;

use App\Models\Currency;
use App\Services\SettingService;
class CurrencyService
{
    public function getDefaultCurrency()
    {

        static $currency = null;

        if ($currency != null) {
            return $currency;
        }

        // Fetch default currency using Eloquent
        $currency = Currency::where('is_default', 1)->first();



        return $currency;
    }
    public function getAllCurrency()
    {


        static $currencies = null;

        if ($currencies != null) {
            return $currencies;
        }


        $currencies = Currency::all()->toArray();



        return $currencies;
    }
    public function getCurrencyCodeSettings($code, $fetchWithSymbol = false)
    {
        static $cache = [];

        $key = ($fetchWithSymbol ? 'symbol:' : 'code:') . $code;

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $query = Currency::select('*');

        if ($fetchWithSymbol) {
            $query->where('symbol', $code);
        } else {
            $query->where('code', $code);
        }

        $currency = $query->get()->toArray();

        $cache[$key] = $currency;

        return $currency;
    }
    public function currentCurrencyPrice($price, $with_symbol = false)
    {
        $system_settings = app(SettingService::class)->getSettings('system_settings', true);
        $system_settings = json_decode($system_settings, true);
        if (!isset($system_settings['currency_setting'])) {

            $system_settings = app(SettingService::class)->getSettings('system_settings', true);
            $system_settings = json_decode($system_settings, true);
        }
        $currency_code = $system_settings['currency_setting']['code'];

        $currency_details = $this->getCurrencyCodeSettings($currency_code);
        // dd($currency_details);
        $currency_symbol = $currency_details[0]->symbol ?? $system_settings['currency_setting']['symbol'];
        $amount = (float) $price * number_format((float) $currency_details[0]['exchange_rate'], 2);
        if ($with_symbol == true) {
            return $currency_symbol . number_format($amount, 2);
        }
        return $amount;
    }
    public function getPriceCurrency($price)
    {
        $currencies = app(CurrencyService::class)->getAllCurrency();
        // dd($currencies);
        $rows = [];

        foreach ($currencies as $currency) {
            // Make sure $currency is an object
            // if (!is_object($currency)) {
            //     continue; // skip if it's not an object
            // }

            // Calculate the amount in target currency
            $exchangeRate = (float) $currency['exchange_rate'];
            $amount = (float) $price * $exchangeRate;

            // Format and build the result row
            $rows[$currency['code']] = [
                'currency_code' => $currency['code'],
                'symbol' => $currency['symbol'],
                'exchange_rate' => number_format($exchangeRate, 2),
                'amount' => formatePriceDecimal($amount)
            ];
        }

        return $rows;
    }

    public function formateCurrency($price, $currency = '', $before = true)
    {
        $baseCurrency = app(CurrencyService::class)->getDefaultCurrency()->symbol;

        $currency_symbol = isset($currency) && !empty($currency) ? $currency : $baseCurrency;
        if ($before == true) {
            return $currency_symbol . $price;
        } else {
            return $price . $currency_symbol;
        }
    }
}