<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Address;
use App\Models\City;
use App\Models\Zipcode;
use App\Models\ComboProduct;
use App\Models\Store;
use App\Models\Zone;
use App\Models\Area;
use App\Models\Product;
use App\Models\SellerStore;
use App\Models\PickupLocation;
use App\Libraries\Shiprocket;
use App\Services\CurrencyService;
use App\Services\SettingService;
class DeliveryService
{
    public function getDeliveryChargeSetting($store_id)
    {
        $res = fetchDetails(Store::class, ['id' => $store_id], ['delivery_charge_type', 'delivery_charge_amount', 'minimum_free_delivery_amount', 'product_deliverability_type']);
        if (!$res->isEmpty()) {
            return $res;
        } else {
            return false;
        }
    }
    public function getDeliveryCharge($address_id, $total = 0, $cartData = [], $store_id = "")
    {
        // dd($cartData);
        if (isset($cartData) && isset($cartData[0]['type']) && !empty($cartData[0]['type'])) {
            $has_digital_product = !empty(array_filter($cartData, function ($item) {
                return isset($item['type']) && $item['type'] === 'digital_product';
            }));

            if ($has_digital_product) {
                return number_format(0, 2);
            }
        }


        $total = str_replace(',', '', $total);

        $settings = $this->getDeliveryChargeSetting($store_id);

        $address = Address::where('id', $address_id)->value('pincode');
        $address_city = Address::where('id', $address_id)->value('city');
        // dd($settings[0]->product_deliverability_type);
        if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
            if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                // dd($settings[0]->delivery_charge_type);
                if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'city_wise_delivery_charge') {

                    if (isset($address_city) && !empty($address_city)) {
                        $city = $city = City::where("name->en", $address_city)
                            ->select('delivery_charges', 'minimum_free_delivery_order_amount')
                            ->first();
                        if ($city && isset($city->minimum_free_delivery_order_amount)) {
                            $min_amount = $city->minimum_free_delivery_order_amount;
                            $delivery_charge = $city->delivery_charges;
                        }
                        $d_charge = intval($total) < $min_amount || $total === 0 ? $delivery_charge : 0;

                        return number_format($d_charge, 2);
                    }
                } else if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'global_delivery_charge') {

                    $min_amount = $settings[0]->minimum_free_delivery_amount;
                    $delivery_charge = $settings[0]->delivery_charge_amount;
                    $d_charge = intval($total) < $min_amount || $total === 0 ? $delivery_charge : 0;

                    return number_format($d_charge, 2);
                } else if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'product_wise_delivery_charge') {
                    $d_charge = [];
                    foreach ($cartData as $row) {
                        // dd($row['product_qty'] < $row['minimum_free_delivery_order_qty']);
                        // $temp['delivery_charge'] = $row['product_qty'] < $row['minimum_free_delivery_order_qty'] ? number_format($row['product_delivery_charge'], 2) : [];
                        $temp['delivery_charge'] = number_format($row['product_delivery_charge'], 2);
                        array_push($d_charge, $temp);
                    }
                    return $d_charge;
                }
            } else {

                if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'zipcode_wise_delivery_charge') {

                    if (isset($address) && !empty($address)) {
                        $zipcode = Zipcode::where('zipcode', $address)->select('delivery_charges', 'minimum_free_delivery_order_amount')->first();

                        if ($zipcode) {
                            $min_amount = $zipcode->minimum_free_delivery_order_amount ?? 0;
                            $delivery_charge = $zipcode->delivery_charges ?? 0;

                            $d_charge = intval($total) < $min_amount || $total == 0 ? $delivery_charge : 0;
                            return number_format($d_charge, 2);
                        } else {
                            // No zipcode found, handle safely
                            return number_format(0, 2);
                        }
                    } else {
                        // Address empty, handle safely
                        return number_format(0, 2);
                    }
                } else if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'global_delivery_charge') {
                    $min_amount = $settings[0]->minimum_free_delivery_amount;
                    $delivery_charge = $settings[0]->delivery_charge_amount;
                    $d_charge = intval($total) < $min_amount || $total === 0 ? $delivery_charge : 0;
                    return number_format($d_charge, 2);
                } else if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'product_wise_delivery_charge') {
                    $d_charge = [];
                    foreach ($cartData as $row) {
                        $temp['delivery_charge'] = number_format((float) $row['product_delivery_charge'], 2);
                        array_push($d_charge, $temp);
                    }
                    return $d_charge;
                }
            }
        }
    }

    public function isProductDelivarable($type, $type_id, $product_id, $product_type = '')
    {
        $zipcode_id = null;
        $city_id = null;

        // Determine location
        switch ($type) {
            case 'zipcode':
                $zipcode_id = $type_id;
                break;
            case 'area':
                $zipcode_id = Area::where('id', $type_id)->value('zipcode_id');
                break;
            case 'city':
                $city_id = $type_id;
                break;
            default:
                return false;
        }

        $isCombo = in_array($product_type, ['combo', 'combo-product']);
        $model = $isCombo ? ComboProduct::class : Product::class;
        $table = $isCombo ? 'combo_products' : 'products';

        // Get zones only if needed
        $zone_ids = [];

        // Perform query
        $isDeliverable = $model::join('seller_store', "$table.seller_id", '=', 'seller_store.seller_id')
            ->where("$table.id", $product_id)
            ->where(function ($query) use ($type, $type_id, $table, $model, $product_id, &$zone_ids, $zipcode_id, $city_id) {
                // Always allow deliverable_type = 1
                $query->where("$table.deliverable_type", 1);
                // dd($product_id);
                // Add condition for deliverable_type = 2 only if zone_ids exist
                $query->orWhere(function ($q) use ($table, $zipcode_id, $city_id, $model, $product_id, &$zone_ids) {
                    // Get zone_ids only here
                    if ($zipcode_id) {
                        $zone_ids = $this->getZonesServiceableByZipcode($this->getDeliverableZones($model, $product_id), $zipcode_id);
                    } elseif ($city_id) {
                        $zone_ids = $this->getZonesServiceableByCity($this->getDeliverableZones($model, $product_id), $city_id);
                    }

                    if (!empty($zone_ids)) {
                        $q->where("$table.deliverable_type", 2)
                            ->where(function ($inner) use ($zone_ids, $table) {
                                foreach ($zone_ids as $zoneId) {
                                    $inner->orWhereRaw("FIND_IN_SET(?, $table.deliverable_zones)", [$zoneId]);
                                }
                            });
                    } else {
                        // if zone_ids are empty, this OR condition is ignored
                        $q->whereRaw('0 = 1');
                    }
                });
            });

        // dd($isDeliverable->toSql(), $isDeliverable->getBindings());
        return $isDeliverable->exists();
    }


    public function isSellerDeliverable($type, $type_id, $seller_id, $store_id = '')
    {
        if ($type == 'zipcode') {
            $zipcode_id = $type_id;
        } elseif ($type == 'area') {
            $zipcode_id = Area::where('id', $type_id)->value('zipcode_id');
        } elseif ($type == 'city') {
            $city_id = $type_id;
        } else {
            return false;
        }


        if (!empty($zipcode_id) && $zipcode_id != 0) {
            // dd('here');
            $deliverable_zones = $this->getSellerDeliverableZones($seller_id, $store_id);

            $seller_store = SellerStore::where('seller_id', $seller_id)->where('store_id', $store_id)->first();
            if ($seller_store) {
                if ($seller_store->deliverable_type == 1) {
                    $all_zones = Zone::where('status', 1)->pluck('id')->toarray();
                    $product = $this->getZonesServiceableByZipcode($all_zones, $zipcode_id);
                    return $product > 0;
                } else {
                    // Check using FIND_IN_SET to match within comma-separated values
                    $zones_serviceable_zipcodes = $this->getZonesServiceableByZipcode($deliverable_zones, $zipcode_id);
                    if (count($zones_serviceable_zipcodes) == 1) {
                        if ($zones_serviceable_zipcodes) {
                            $product = SellerStore::whereRaw("FIND_IN_SET(?, deliverable_zones)", [$zones_serviceable_zipcodes])
                                ->where('seller_id', $seller_id)
                                ->where('store_id', $store_id)
                                ->count();


                            return $product > 0;
                        }
                    } else {
                        if ($zones_serviceable_zipcodes) {
                            $product = SellerStore::where('store_id', $store_id)->where('seller_id', $seller_id)
                                ->where(function ($query) use ($zones_serviceable_zipcodes) {
                                    $query->where(function ($subquery) use ($zones_serviceable_zipcodes) {
                                        $subquery->where("seller_store.deliverable_type", '2')
                                            ->whereIn("seller_store.deliverable_zones", $zones_serviceable_zipcodes);
                                    });
                                })
                                ->count();
                            return $product > 0;
                        }
                    }
                    return false;
                }
            }
        } elseif (!empty($city_id) && $city_id != 0) {
            $deliverable_zones = $this->getSellerDeliverableZones($seller_id, $store_id);
            $seller_store = SellerStore::where('seller_id', $seller_id)->where('store_id', $store_id)->first();
            if ($seller_store) {
                if ($seller_store->deliverable_type == 1) {
                    $all_zones = Zone::where('status', 1)->pluck('id')->toarray();
                    $product = $this->getZonesServiceableByCity($all_zones, $city_id);
                    return $product > 0;
                } else {
                    // Check using FIND_IN_SET to match within comma-separated values
                    $zones_serviceable_cities = $this->getZonesServiceableByCity($deliverable_zones, $city_id);
                    if (count($zones_serviceable_cities) == 1) {
                        // dd('here');
                        $product = SellerStore::whereRaw("FIND_IN_SET(?, deliverable_zones)", [$zones_serviceable_cities])
                            ->where('seller_id', $seller_id)
                            ->where('store_id', $store_id)
                            ->count();
                        // dd($product);
                        return $product > 0;
                    } else {
                        if ($zones_serviceable_cities) {
                            $product = SellerStore::where('store_id', $store_id)->where('seller_id', $seller_id)
                                ->where(function ($query) use ($zones_serviceable_cities) {
                                    $query->where(function ($subquery) use ($zones_serviceable_cities) {
                                        $subquery->where("seller_store.deliverable_type", '2')
                                            ->whereIn("seller_store.deliverable_zones", $zones_serviceable_cities);
                                    });
                                })
                                ->count();
                            // dd($product);
                            return $product > 0;
                        }
                    }
                    // return false;
                }
            }
        } else {
            return false;
        }
    }
    public function getSellerDeliverableZones($seller_id, $store_id)
    {
        $seller_deliverable_data = fetchDetails(SellerStore::class, ['seller_id' => $seller_id, 'store_id' => $store_id], 'deliverable_zones');
        return !$seller_deliverable_data->isEmpty() ? explode(',', $seller_deliverable_data[0]->deliverable_zones) : [];
    }
    public function getDeliverableZonesOld($productTypeTable, $productId)
    {
        $deliverable_zones = fetchDetails($productTypeTable, ['id' => $productId], 'deliverable_zones');
        return !$deliverable_zones->isEmpty() ? explode(',', $deliverable_zones[0]->deliverable_zones) : [];
    }

    public function getDeliverableZones(string $modelClass, ?int $productId): array
    {
        if (is_null($productId)) {
            return [];
        }

        $product = $modelClass::find($productId);

        if (!$product || empty($product->deliverable_zones)) {
            return [];
        }

        return explode(',', $product->deliverable_zones);
    }

    public function getZonesServiceableByZipcode($deliverableZones, $zipcodeId)
    {
        return Zone::whereIn('id', $deliverableZones)
            ->where('status', 1)
            ->get(['id', 'serviceable_zipcode_ids'])
            ->filter(function ($zone) use ($zipcodeId) {
                return in_array($zipcodeId, explode(',', $zone->serviceable_zipcode_ids));
            })
            ->pluck('id')
            ->all();
    }

    public function getZonesServiceableByCity($deliverableZones, $cityId)
    {
        return Zone::whereIn('id', $deliverableZones)
            ->where('status', 1)
            ->get(['id', 'serviceable_city_ids'])
            ->filter(function ($zone) use ($cityId) {
                return in_array($cityId, explode(',', $zone->serviceable_city_ids));
            })
            ->pluck('id')
            ->all();
    }

    public function checkProductDeliverable($product_id, $zipcode = "", $zipcode_id = "", $store_id = '', $city_id = "", $product_type = 'regular')
    {
        $products = $tmpRow = array();
        $settings = app(SettingService::class)->getSettings('shipping_method', true);
        $settings = json_decode($settings, true);
        $product_weight = 0;
        if ($product_type == "combo") {
            $product = app(ComboProductService::class)->fetchComboProduct(id: $product_id);
        } else {
            $product = app(ProductService::class)->fetchProduct(id: $product_id);
        }
        /* check in local shipping first */
        $tmpRow['is_deliverable'] = false;
        $tmpRow['delivery_by'] = '';
        if (isset($product['total']) && $product['total'] >= 1) {
            if ($product_type == "combo") {
                $product = $product['combo_product'][0];
            } else {
                $product = $product['product'][0];
            }
            if (isset($settings['local_shipping_method']) && $settings['local_shipping_method'] == 1) {

                $deliverabilitySettings = $this->getDeliveryChargeSetting($store_id);
                if (isset($deliverabilitySettings[0]->product_deliverability_type) && !empty($deliverabilitySettings[0]->product_deliverability_type)) {
                    if ($deliverabilitySettings[0]->product_deliverability_type == 'city_wise_deliverability') {
                        $tmpRow['is_deliverable'] = (!empty($city_id) && $city_id > 0) ?
                            $this->isProductDelivarable('city', $city_id, $product->id, $product_type)
                            : false;
                    } else {

                        $tmpRow['is_deliverable'] = !empty($zipcode_id) && $zipcode_id > 0 ?
                            $this->isProductDelivarable('zipcode', $zipcode_id, $product->id, $product_type) :
                            false;
                    }
                }


                $tmpRow['delivery_by'] = isset($tmpRow['is_deliverable']) && $tmpRow['is_deliverable'] ? 'local' : '';
            }
            /* check in standard shipping then */
            if (isset($settings['shiprocket_shipping_method']) && $settings['shiprocket_shipping_method'] == 1) {

                if (!$tmpRow['is_deliverable'] && $product->pickup_location != "") {
                    $shiprocket = new Shiprocket();
                    $pickup_pincode = fetchDetails(PickupLocation::class, ['pickup_location' => $product->pickup_location], 'pincode');
                    $product_weight += $product->variants[0]->weight * 1;

                    if (isset($zipcode) && !empty($zipcode)) {
                        if ($product_weight > 15) {
                            $tmpRow['is_deliverable'] = false;
                            $tmpRow['is_valid_wight'] = 0;
                            $tmpRow['message'] = "You cannot ship weight more then 15 KG";
                        } else {
                            $availibility_data = [
                                'pickup_postcode' => !$pickup_pincode->isEmpty() ? $pickup_pincode[0]->pincode : "",
                                'delivery_postcode' => $zipcode,
                                'cod' => 0,
                                'weight' => $product_weight,
                            ];


                            $check_deliveribility = $shiprocket->check_serviceability($availibility_data);
                            if (isset($check_deliveribility['status_code']) && $check_deliveribility['status_code'] == 422) {
                                $tmpRow['is_deliverable'] = false;
                                $tmpRow['message'] = "Invalid zipcode supplied!";
                            } else {

                                if (isset($check_deliveribility['status']) && $check_deliveribility['status'] == 200 && !empty($check_deliveribility['data']['available_courier_companies'])) {
                                    $tmpRow['is_deliverable'] = true;
                                    $tmpRow['delivery_by'] = "standard_shipping";
                                    $estimate_date = $check_deliveribility['data']['available_courier_companies'][0]['etd'];
                                    $tmpRow['estimate_date'] = $estimate_date;
                                    $_SESSION['valid_zipcode'] = $zipcode;
                                    $tmpRow['message'] = 'Product is deliverable by ' . $estimate_date;
                                } else {
                                    $tmpRow['is_deliverable'] = false;
                                    $tmpRow['message'] = $check_deliveribility['message'];
                                }
                            }
                        }
                    } else {
                        $tmpRow['is_deliverable'] = false;
                        $tmpRow['message'] = 'Please select zipcode to check the deliveribility of item.';
                    }
                }
            }

            $tmpRow['product_id'] = $product->id;
            $tmpRow['product_qty'] = 1;
            $products[] = $tmpRow;
            if (!empty($products)) {
                return $products;
            } else {
                return false;
            }
        }
    }

    public function checkCartProductsDeliverable($user_id, $zipcode = "", $zipcode_id = "", $store_id = '', $city = "", $city_id = "", $is_saved_for_later = 0, $language_code = '')
    {
        $products = $tmpRow = array();
        // $cart = getCartTotal($user_id, false, $is_saved_for_later, '', $store_id);
        $cart = app(CartService::class)->getCartTotal($user_id, false, $is_saved_for_later, '', $store_id);
        // dd($cart);
        $settings = app(SettingService::class)->getSettings('shipping_method', true);
        $settings = json_decode($settings, true);

        if (!$cart->isEmpty()) {

            $product_weight = 0;

            for ($i = 0; $i < $cart[0]->cart_count; $i++) {
                /* check in local shipping first */
                $tmpRow['is_deliverable'] = false;
                $tmpRow['delivery_by'] = '';
                if (isset($settings['local_shipping_method']) && $settings['local_shipping_method'] == 1) {
                    $deliverabilitySettings = $this->getDeliveryChargeSetting($store_id);
                    if (isset($deliverabilitySettings[0]->product_deliverability_type) && !empty($deliverabilitySettings[0]->product_deliverability_type)) {

                        if ($deliverabilitySettings[0]->product_deliverability_type == 'city_wise_deliverability') {

                            $seller_deliverable = (!empty($city_id) && $city_id > 0) ? $this->isSellerDeliverable('city', $city_id, $cart[$i]['product']['seller_id'], $store_id) : false;

                            if ($seller_deliverable) {
                                $tmpRow['is_deliverable'] = (!empty($city_id) && $city_id > 0) ?
                                    $this->isProductDelivarable('city', $city_id, $cart[$i]['product']['id'], $cart[$i]->cart_product_type)
                                    : false;
                            } else {
                                $tmpRow['is_deliverable'] = false;
                            }
                        } else {
                            $seller_deliverable = (!empty($zipcode_id) && $zipcode_id > 0) ? $this->isSellerDeliverable('zipcode', $zipcode_id, $cart[$i]['product']['seller_id'], $store_id) : false;
                            if ($seller_deliverable) {
                                $tmpRow['is_deliverable'] = !empty($zipcode_id) && $zipcode_id > 0 ?
                                    $this->isProductDelivarable('zipcode', $zipcode_id, $cart[$i]['product']['id'], $cart[$i]->cart_product_type) :
                                    false;
                            } else {
                                $tmpRow['is_deliverable'] = false;
                            }
                        }
                    }

                    $tmpRow['delivery_by'] = isset($tmpRow['is_deliverable']) && $tmpRow['is_deliverable'] ? 'local' : '';
                }

                /* check in standard shipping then */
                if (isset($settings['shiprocket_shipping_method']) && $settings['shiprocket_shipping_method'] == 1) {


                    if (!$tmpRow['is_deliverable'] && $cart[$i]['product']['pickup_location'] != "") {
                        $shiprocket = new Shiprocket();
                        $pickup_pincode = fetchDetails(PickupLocation::class, ['pickup_location' => $cart[$i]['product']['pickup_location']], 'pincode');
                        $product_weight += $cart[$i]->weight * $cart[$i]->qty;

                        if (isset($zipcode) && !empty($zipcode)) {
                            if ($product_weight > 15) {
                                $tmpRow['is_deliverable'] = false;
                                $tmpRow['is_valid_wight'] = 0;
                                $tmpRow['message'] = "You cannot ship weight more then 15 KG";
                            } else {
                                $availibility_data = [
                                    'pickup_postcode' => !$pickup_pincode->isEmpty() ? $pickup_pincode[0]->pincode : "",
                                    'delivery_postcode' => $zipcode,
                                    'cod' => 0,
                                    'weight' => $product_weight,
                                ];


                                $check_deliveribility = $shiprocket->check_serviceability($availibility_data);

                                if (isset($check_deliveribility['status_code']) && $check_deliveribility['status_code'] == 422) {
                                    $tmpRow['is_deliverable'] = false;
                                    $tmpRow['message'] = "Invalid zipcode supplied!";
                                } else {

                                    if (isset($check_deliveribility['status']) && $check_deliveribility['status'] == 200 && !empty($check_deliveribility['data']['available_courier_companies'])) {
                                        $tmpRow['is_deliverable'] = true;
                                        $tmpRow['delivery_by'] = "standard_shipping";
                                        $estimate_date = $check_deliveribility['data']['available_courier_companies'][0]['etd'];
                                        $tmpRow['estimate_date'] = $estimate_date;
                                        $_SESSION['valid_zipcode'] = $zipcode;
                                        $tmpRow['message'] = 'Product is deliverable by ' . $estimate_date;
                                    } else {
                                        $tmpRow['is_deliverable'] = false;
                                        $tmpRow['message'] = $check_deliveribility['message'];
                                    }
                                }
                            }
                        } else {
                            $tmpRow['is_deliverable'] = false;
                            $tmpRow['message'] = 'Please select zipcode to check the deliveribility of item.';
                        }
                    }
                }


                // dd($cart[$i]['product']);
                $tmpRow['product_id'] = $cart[$i]['product']['id'];
                $tmpRow['product_qty'] = $cart[$i]->qty;
                // comment because if qty and charges added in cart still showing null 

                // $tmpRow['minimum_free_delivery_order_qty'] = $cart[$i]->minimum_free_delivery_order_qty;
                // $tmpRow['product_delivery_charge'] = $cart[$i]->product_delivery_charge;
                $tmpRow['minimum_free_delivery_order_qty'] = $cart[$i]['product']['minimum_free_delivery_order_qty'];
                $tmpRow['product_delivery_charge'] = $cart[$i]['product']['delivery_charges'];
                $tmpRow['currency_product_delivery_charge_data'] = isset($cart[$i]->product_delivery_charge) ? app(CurrencyService::class)->getPriceCurrency($cart[$i]->product_delivery_charge) : 0;

                $tmpRow['variant_id'] = $cart[$i]['product_variant_id'];

                if ($cart[$i]->cart_product_type === 'regular') {
                    // Get name from products table
                    $tmpRow['name'] = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $cart[$i]['product']['id'], $language_code);
                } else {
                    // Get name from combo_products table
                    $tmpRow['name'] = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $cart[$i]['product']['id'], $language_code);
                }


                $products[] = $tmpRow;
            }


            if (!empty($products)) {

                return $products;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
    public function recalulateDeliveryCharge($address_id, $total, $old_delivery_charge, $store_id = '')
    {

        $settings = $this->getDeliveryChargeSetting($store_id);

        $min_amount = $settings[0]->minimum_free_delivery_amount;
        $d_charge = $old_delivery_charge;

        if ((isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'zipcode_wise_delivery_charge')) {


            if (isset($address_id) && !empty($address_id)) {
                $address = Address::where('id', $address_id)->value('pincode');
                $zipcode = Zipcode::where('zipcode', $address)->select('delivery_charges', 'minimum_free_delivery_order_amount')->first();

                if ($zipcode && isset($zipcode->minimum_free_delivery_order_amount)) {
                    $min_amount = $zipcode->minimum_free_delivery_order_amount;
                }
            }
        }

        if ($total < $min_amount) {
            if ($old_delivery_charge == 0) {
                if (isset($address_id) && !empty($address_id)) {
                    $d_charge = $this->getDeliveryCharge($address_id, '', '', $store_id);
                } else {
                    $d_charge = 0;
                }
            }
        }

        return $d_charge;
    }
}