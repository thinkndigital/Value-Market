<?php

namespace App\Services;

use App\Models\Store;
class StoreService
{
    public function getStoreSettings()
    {
        $store_id = session('store_id');
        $store_settings = null;
        if (!empty($store_id)) {
            $store_settings = $this->getCurrentStoreData($store_id);
            if ($store_settings !== null && $store_settings !== []) {
                $store_settings = json_decode($store_settings, true);
                $store_settings = $store_settings[0]['store_settings'];
            }
        }
        return $store_settings;
    }
    public function getCurrentStoreData($store_id)
    {
        $store_details = session('store_details');
        if ($store_details !== null && json_decode($store_details)[0]->id == $store_id) {
            $store_details = session('store_details');
        } else {
            $store_details = Store::where('id', $store_id)
                ->where('status', 1)
                ->get();
            session()->forget("store_details");
            session()->put("store_details", json_encode($store_details));
        }

        return $store_details;
    }
    public function getStoreId()
    {
        return session('store_id') !== null && !empty(session('store_id')) ? session('store_id') : "";
    }

}