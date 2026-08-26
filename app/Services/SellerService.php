<?php

namespace App\Services;

use App\Models\SellerStore;
use App\Models\Zipcode;
use App\Models\Zone;
use App\Models\City;
use App\Models\Category;
use App\Models\CustomField;
use Illuminate\Support\Facades\Session;
use App\Services\TranslationService;
use App\Services\MediaService;
class SellerService
{
    public function formatSellerData($seller_data, $isPublicDisk)
    {
        for ($k = 0; $k < count($seller_data); $k++) {
            $seller_data[$k]['national_identity_card'] = $isPublicDisk ? asset(config('constants.SELLER_IMG_PATH') . $seller_data[$k]['national_identity_card']) : $seller_data[$k]['national_identity_card'];
            $seller_data[$k]['authorized_signature'] = $isPublicDisk ? asset(config('constants.SELLER_IMG_PATH') . $seller_data[$k]['authorized_signature']) : $seller_data[$k]['authorized_signature'];
        }
        return $seller_data;
    }
    public function getSellerPermission($seller_id, $store_id, $permit = NULL)
    {
        // Check if $seller_id is provided, otherwise get it from session
        $seller_id = (isset($seller_id) && !empty($seller_id)) ? $seller_id : Session::get('user_id');

        // Fetch seller store details
        $permits = fetchDetails(SellerStore::class, ['seller_id' => $seller_id, 'store_id' => $store_id], 'permissions');

        // Check if $permits is not empty and has the necessary permissions data
        if (!$permits->isEmpty()) {
            // If a specific permit is requested
            if (!empty($permit)) {
                $s_permits = json_decode($permits[0]->permissions, true);

                // Check if the requested permit exists in the permissions array
                return isset($s_permits[$permit]) ? $s_permits[$permit] : null;
            } else {
                // Return all permissions if no specific permit is requested
                return json_decode($permits[0]->permissions);
            }
        } else {
            // Handle case where $permits is empty or invalid
            return null; // Or return a default value like false if needed
        }
    }

    public function formatUserData($user, $fcm_ids_array)
    {
        return [
            'user_id' => $user->id ?? '',
            'ip_address' => $user->ip_address ?? '',
            'username' => $user->username ?? '',
            'email' => $user->email ?? '',
            'mobile' => $user->mobile ?? '',
            'image' => app(MediaService::class)->getMediaImageUrl($user->image, 'SELLER_IMG_PATH'),
            'balance' => $user->balance ?? '0',
            'activation_selector' => $user->activation_selector ?? '',
            'activation_code' => $user->activation_code ?? '',
            'forgotten_password_selector' => $user->forgotten_password_selector ?? '',
            'forgotten_password_code' => $user->forgotten_password_code ?? '',
            'forgotten_password_time' => $user->forgotten_password_time ?? '',
            'remember_selector' => $user->remember_selector ?? '',
            'remember_code' => $user->remember_code ?? '',
            'created_on' => $user->created_on ?? '',
            'last_login' => $user->last_login ?? '',
            'active' => $user->active ?? '',
            'is_notification_on' => $user->is_notification_on ?? '',
            'company' => $user->company ?? '',
            'address' => $user->address ?? '',
            'bonus' => $user->bonus ?? '',
            'cash_received' => $user->cash_received ?? '0.00',
            'dob' => $user->dob ?? '',
            'country_code' => $user->country_code ?? '',
            'city' => $user->city ?? '',
            'area' => $user->area ?? '',
            'street' => $user->street ?? '',
            'pincode' => $user->pincode ?? '',
            'apikey' => $user->apikey ?? '',
            'referral_code' => $user->referral_code ?? '',
            'friends_code' => $user->friends_code ?? '',
            'fcm_id' => array_values($fcm_ids_array) ?? '',
            'latitude' => $user->latitude ?? '',
            'longitude' => $user->longitude ?? '',
            'created_at' => $user->created_at ?? '',
            'type' => $user->type ?? '',
        ];
    }

    public function formatStoreData($store_data, $isPublicDisk, $language_code = '')
    {
        for ($i = 0; $i < count($store_data); $i++) {
            $store = $store_data[$i];
            // Zones
            $zone_ids = explode(',', $store->deliverable_zones);
            $zones = Zone::whereIn('id', $zone_ids)->get();
            $translated_zones = $zones->map(function ($zone) use ($language_code) {
                return app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code);
            })->toArray();

            $store->zones = implode(',', $translated_zones) ?? '';
            $store->zone_ids = $store->deliverable_zones ?? '';

            // City
            $store->city_id = $store->city ?? "";
            $store->city = $store->city ? app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $store->city, $language_code) : '';

            // Zipcode
            $store->zipcode_id = $store->zipcode ?? "";
            $store->zipcode = $store->zipcode ? Zipcode::where('id', $store->zipcode)->value('zipcode') : "";

            // Images
            $store->logo = $isPublicDisk ? asset(config('constants.SELLER_IMG_PATH') . $store->logo) : $store->logo;
            $store->address_proof = $isPublicDisk ? asset(config('constants.SELLER_IMG_PATH') . $store->address_proof) : $store->address_proof;
            $store->store_thumbnail = $isPublicDisk ? asset(config('constants.SELLER_IMG_PATH') . $store->store_thumbnail) : $store->store_thumbnail;

            // Other documents
            $store->other_documents = $isPublicDisk
                ? (is_array($decoded = json_decode((string) $store->other_documents, true)) ? array_map(fn($document) => asset(config('constants.SELLER_IMG_PATH') . '/' . $document), $decoded) : [])
                : (json_decode((string) $store->other_documents, true) ?: []);

            // Permissions
            $store->permissions = json_decode($store->permissions, true);

            // ✅ Custom fields
            
            $customFields = $customFields = CustomField::where('store_id', $store->store_id)
                ->where('active', 1)
                ->get();
            $store->custom_fields = $customFields
                ->where('active', 1)
                ->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'name' => $field->name,
                        'type' => $field->type,
                        'field_length' => $field->field_length,
                        'min' => $field->min,
                        'max' => $field->max,
                        'required' => $field->required,
                        'active' => $field->active,
                        'options' => is_array($field->options)
                            ? $field->options
                            : (json_decode($field->options, true) ?? []),
                    ];
                })->values();
        }

        return $store_data;
    }

    public function getSellerCategories($seller_id)
    {
        $store_id = app(StoreService::class)->getStoreId();

        $level = 0;
        $seller_id = isset($seller_id) ? $seller_id : '';


        $seller_data = SellerStore::select('seller_store.category_ids')
            ->where('seller_store.store_id', $store_id)
            ->where('seller_store.seller_id', $seller_id)
            ->get();



        if ($seller_data->isEmpty()) {
            return [];
        }

        $category_ids = explode(",", $seller_data[0]->category_ids);

        $categories = Category::whereIn('id', $category_ids)
            ->where('status', 1)
            ->get()
            ->toArray();

        foreach ($categories as &$p_cat) {
            $p_cat['children'] = subCategories($p_cat['id'], $level);
            $p_cat['text'] = e($p_cat['name']);
            $p_cat['name'] = e($p_cat['name']);
            $p_cat['state'] = ['opened' => true];
            $p_cat['icon'] = "jstree-folder";
            $p_cat['level'] = $level;
            $p_cat['image'] = app(MediaService::class)->getImageUrl($p_cat['image'], 'thumb', 'md');
            $p_cat['banner'] = app(MediaService::class)->getImageUrl($p_cat['banner'], 'thumb', 'md');
        }

        if (!empty($categories)) {
            $categories[0]['total'] = count($category_ids);
        }

        return $categories;
    }
}