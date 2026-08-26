<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Address;
use App\Models\City;
use App\Models\Zipcode;
use App\Models\ComboProduct;
use App\Models\Product_variants;
use Illuminate\Support\Str;
use App\Services\DeliveryService;
use App\Services\MediaService;
use App\Services\CurrencyService;
use App\Services\SettingService;
class CartService
{
    public function addToCart($data, $check_status = true, $fromApp = false)
    {
        $data = array_map('htmlspecialchars', $data);
        $product_type = $data['product_type'] != null ? explode(',', Str::lower($data['product_type'])) : [];
        $product_variant_ids = explode(',', $data['product_variant_id']);
        $store_id = explode(',', $data['store_id']);
        $qtys = explode(',', $data['qty']);

        if ($check_status == true) {

            $check_current_stock_status = validateStock($product_variant_ids, $qtys, $product_type);
            if (!empty($check_current_stock_status) && $check_current_stock_status['error'] == true) {
                return $check_current_stock_status;
            }
        }

        foreach ($product_variant_ids as $index => $product_variant_id) {
            $cart_data = [
                'user_id' => $data['user_id'],
                'product_variant_id' => $product_variant_id,
                'qty' => $qtys[$index],
                'is_saved_for_later' => (isset($data['is_saved_for_later']) && !empty($data['is_saved_for_later']) && $data['is_saved_for_later'] == '1') ? $data['is_saved_for_later'] : '0',
                'store_id' => (isset($store_id) && !empty($store_id)) ? $store_id[$index] : '',
                'product_type' => (isset($product_type) && !empty($product_type)) ? $product_type[$index] : '',
            ];
            if ($qtys[$index] == 0) {

                $this->removeFromCart($cart_data);
            } else {
                $existing_cart_item = Cart::where(['user_id' => $data['user_id'], 'product_variant_id' => $product_variant_id])->first();


                if (!empty($existing_cart_item) && $existing_cart_item != null) {

                    $existing_cart_item->update($cart_data);

                    if ($fromApp == true) {

                        return true;
                    } else {
                        return true;
                    }
                } else {
                    Cart::create($cart_data);
                    if ($fromApp == true) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function removeFromCart($data)
    {
        $is_saved_for_later = isset($data['is_saved_for_later']) ? $data['is_saved_for_later'] : 0;
        if (isset($data['user_id']) && !empty($data['user_id'])) {
            $query = Cart::where('user_id', $data['user_id']);

            if (isset($data['product_variant_id'])) {
                $product_variant_ids = explode(',', $data['product_variant_id']);
                $query->whereIn('product_variant_id', $product_variant_ids);
            }
            if (isset($data['product_type'])) {
                $product_types = explode(',', $data['product_type']);
                $query->whereIn('product_type', $product_types);
            }
            $query->where('store_id', $data['store_id']);
            $query->where('is_saved_for_later', $is_saved_for_later);

            return $query->delete();
        } else {
            return false;
        }
    }

    public function getCartTotal($user_id, $product_variant_id = false, $is_saved_for_later = 0, $address_id = '', $store_id = '')
    {
        $query = [];
        // get product details from products table
        $productQuery = Cart::with([
            'productVariant.product' => function ($query) {
                $query->with('sellerData', 'category');
            }
        ])
            ->where('user_id', $user_id)
            ->where('qty', '>=', 0)
            ->where('is_saved_for_later', intval($is_saved_for_later))
            ->where('store_id', $store_id)
            ->where('product_type', 'regular')
            ->whereHas('productVariant.product', function ($query) {
                $query->where('status', 1)->whereHas('sellerData', function ($q) {
                    $q->where('status', 1);
                });
            })
            ->whereHas('productVariant', function ($query) {
                $query->where('status', 1);
            })
            ->when($product_variant_id, function ($query) use ($product_variant_id) {
                $query->where('product_variant_id', $product_variant_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        // get product details from combo_products table

        $comboProductQuery = Cart::with('comboProduct.sellerData')
            ->where('user_id', $user_id)
            ->where('qty', '>=', 0)
            ->where('is_saved_for_later', intval($is_saved_for_later))
            ->where('store_id', $store_id)
            ->where('product_type', 'combo')
            ->whereHas('comboProduct', function ($query) {
                $query->where('status', 1)->whereHas('sellerData', function ($q) {
                    $q->where('status', 1);
                });
            })
            ->when($product_variant_id, function ($query) use ($product_variant_id) {
                $query->where('product_variant_id', $product_variant_id);
            })
            ->orderBy('id', 'desc')
            ->get();
        $query = $productQuery->merge($comboProductQuery);
        $total = [];
        $item_total = [];
        $variant_id = [];
        $quantity = [];
        $percentage = [];
        $amount = [];
        $cod_allowed = 1;
        $download_allowed = [];
        $totalItems = 0;
        $product_qty = '';
        $product_ids = [];
        $cart_product_type = [];

        if (!$query->isEmpty()) {

            foreach ($query as $result) {
                $totalItems += $result->qty;
            }

            foreach ($query as $i => $item) {
                $type = $item->product_type;
                if ($type == 'combo') {
                    $product = $item->comboProduct;
                } else {
                    $product = $item->product;
                    $category_ids[$i] = $product->category_id;
                }

                $product_ids[$i] = $item->product?->id;
                $cart_product_type[$i] = $type;
                $tax_percentage = $product->getTaxPercentages();
                $tax_titles = $product->getTaxTitles();

                // Set tax info on item
                $item->item_tax_percentage = implode(',', $tax_percentage);
                $item->tax_title = implode(',', $tax_titles);

                // Calculate tax amounts if prices are exclusive of tax
                if (isset($product->is_prices_inclusive_tax) && $product->is_prices_inclusive_tax == 0) {
                    $total_tax = array_sum(array_map('floatval', $tax_percentage));

                    $price_tax_amount = $item->productVariant['price'] * ($total_tax / 100);
                    $special_price_tax_amount = $item->productVariant['special_price'] * ($total_tax / 100);
                } else {
                    $price_tax_amount = 0;
                    $special_price_tax_amount = 0;
                }
                if ($product['cod_allowed'] == 0) {
                    $cod_allowed = 0;
                }
                $variant_id[$i] = $item->product_variant_id;
                $quantity[$i] = intval($item->qty);
                if (($item->productVariant['special_price']) > 0) {
                    $total[$i] = ($item->productVariant['special_price'] + $special_price_tax_amount) * $item->qty;
                } else {
                    $total[$i] = ($item->productVariant['price'] + $price_tax_amount) * $item->qty;
                }
                $item_total[$i] = ($item->productVariant['price'] + $price_tax_amount) * $item->qty;

                $item->productVariant['special_price'] = $item->productVariant['special_price'] + $special_price_tax_amount;
                $item->productVariant['id'] = $item->product_variant_id;
                $item->id = $item->product_variant_id;
                $item->productVariant['price'] = $item->productVariant['price'] + $price_tax_amount;

                $percentage[$i] = (isset($item->tax_percentage) && ($item->tax_percentage) > 0) ? $item->tax_percentage : 0;

                if ($percentage[$i] !== null && $percentage[$i] > 0) {
                    $amount[$i] = !empty($special_price_tax_amount) ? $special_price_tax_amount : $price_tax_amount;
                    $amount[$i] = $amount[$i] * $item->qty;
                } else {
                    $amount[$i] = 0;
                    $percentage[$i] = 0;
                }
                // dd($item->product_type);
                if ($item->product_type != 'combo') {
                    $item->product_variants = app(ProductService::class)->getVariantsValuesById($item->id);
                } else {
                    $item->type = 'combo';
                }
                array_push($download_allowed, $item->download_allowed);

                $item->cart_product_type = $item->product_type;
                $item->cart_count = $query->count();

                $item->total_items = $totalItems;
                $product_qty .= $item->qty . ',';

                $query[$i] = (object) $item;

                $item->image = app(MediaService::class)->getMediaImageUrl($item->image);
                // dd($item->productVariant);
                $items[] = $item;
                // dd($items);
            }

            $total = array_sum($total);
            $item_total = array_sum($item_total);


            $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);

            $shipping_settings = app(SettingService::class)->getSettings('shipping_method', true);
            $shipping_settings = json_decode($shipping_settings, true);

            $delivery_charge = '';
            // dd($address_id);
            if (!empty($address_id)) {
                $address = fetchDetails(Address::class, ['id' => $address_id], ['area_id', 'area', 'pincode', 'city']);
                $pincode = !$address->isEmpty() ? $address[0]->pincode : 0;
                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $address[0]->pincode], 'id');
                $city_id = fetchDetails(City::class, ['name->en' => $address[0]->city], 'id');

                if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
                    if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                        $tmpRow['is_deliverable'] = !$city_id->isEmpty() && $city_id[0]->id > 0 ?
                            app(DeliveryService::class)->isProductDelivarable('city', $city_id[0]->id, $query[0]->product_id, $query[0]->cart_product_type)
                            : false;
                    } else {
                        $tmpRow['is_deliverable'] = !$zipcode_id->isEmpty() && $zipcode_id[0]->id > 0 ?
                            app(DeliveryService::class)->isProductDelivarable('zipcode', $zipcode_id[0]->id, $query[0]->product_id, $query[0]->cart_product_type)
                            : false;
                    }
                }
                // dd('here');
                $tmpRow['delivery_by'] = $tmpRow['is_deliverable'] ? "local" : ((isset($shipping_settings['shiprocket_shipping_method']) && $shipping_settings['shiprocket_shipping_method'] == 1) ? 'standard_shipping' : '');
                if (isset($tmpRow['delivery_by']) && $tmpRow['delivery_by'] === 'standard_shipping') {

                    $parcels = app(ShiprocketService::class)->makeShippingParcels($query);
                    $parcels_details = app(ShiprocketService::class)->checkParcelsDeliverability($parcels, $pincode);
                    $delivery_charge = $parcels_details['delivery_charge_without_cod'];
                } else {
                    // dd($query[0]->product_id);
                    // dd($is_saved_for_later);
                    $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, '', '', $is_saved_for_later);
                    // dd($product_availability);
                    for ($i = 0; $i < count($query); $i++) {
                        $cart[$i]['product_qty'] = $product_availability[$i]['product_qty'];
                        $cart[$i]['minimum_free_delivery_order_qty'] = $product_availability[$i]['minimum_free_delivery_order_qty'];
                        $cart[$i]['product_delivery_charge'] = $product_availability[$i]['product_delivery_charge'];
                        $cart[$i]['currency_product_delivery_charge_data'] = app(CurrencyService::class)->getPriceCurrency($cart[$i]['product_delivery_charge']);
                        if (isset($cart[$i]['delivery_by']) && $cart[$i]['delivery_by'] == "standard_shipping") {
                            $standard_shipping_cart[] = $cart[$i];
                        } else {
                            $local_shipping_cart[] = $cart[$i];
                        }
                    }

                    // dd('here');

                    $delivery_charge = app(DeliveryService::class)->getDeliveryCharge($address_id, $total, $local_shipping_cart, $store_id);

                    // dd($delivery_charge);
                    if (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'product_wise_delivery_charge') {

                        $deliveryCharge = 0;
                        foreach ($delivery_charge as $row) {
                            $deliveryCharge += isset($row['delivery_charge']) && !empty($row['delivery_charge']) ? $row['delivery_charge'] : 0;
                        }
                        $delivery_charge = $deliveryCharge;
                    }
                    // dd($delivery_charge);
                }
            }

            // dd($items);
            $delivery_charge = isset($query[0]->type) && $query[0]->type == 'digital_product' ? 0 : $delivery_charge;
            $discount = $item_total - $total;

            $tax_amount = array_sum($amount);
            $overall_amt = (float) $total + (float) $delivery_charge;
            $query[0]->is_cod_allowed = $cod_allowed;
            $query['sub_total'] = strval($total);
            $query['item_total'] = strval($item_total);
            $query['discount'] = strval($discount);
            $query['currency_sub_total_data'] = app(CurrencyService::class)->getPriceCurrency($query['sub_total']);
            $query['product_quantity'] = $product_qty;
            $query['quantity'] = strval(array_sum($quantity));
            // $query['tax_percentage'] = strval(array_sum($percentage));
            $query['tax_percentage'] = strval(array_sum(array_map('floatval', is_string($percentage) ? explode(',', $percentage) : $percentage)));
            $query['tax_amount'] = strval(array_sum($amount));
            $query['currency_tax_amount_data'] = app(CurrencyService::class)->getPriceCurrency($query['tax_amount']);
            $query['total_arr'] = $total;
            $query['currency_total_arr_data'] = app(CurrencyService::class)->getPriceCurrency($query['total_arr']);
            $query['variant_id'] = $variant_id;
            $query['delivery_charge'] = $delivery_charge;
            $query['currency_delivery_charge_data'] = app(CurrencyService::class)->getPriceCurrency($query['delivery_charge']);
            $query['overall_amount'] = strval($overall_amt);
            $query['currency_overall_amount_data'] = app(CurrencyService::class)->getPriceCurrency($query['overall_amount']);
            $query['amount_inclusive_tax'] = strval($overall_amt + $tax_amount);
            $query['currency_amount_inclusive_tax_data'] = app(CurrencyService::class)->getPriceCurrency($query['amount_inclusive_tax']);
            $query['download_allowed'] = $download_allowed;
            $query['cart_items'] = $items;
        }
        return $query;
    }

    public function isSingleSeller($product_variant_id, $user_id, $product_type = "", $store_id = '')
    {
        if (empty($product_variant_id) || empty($user_id)) {
            return false;
        }

        $variantIds = is_string($product_variant_id) && strpos($product_variant_id, ',') !== false
            ? explode(',', $product_variant_id)
            : (array) $product_variant_id;

        $carts = Cart::with([
            'productVariant.product.sellerData',
            'comboProduct'
        ])
            ->where('user_id', $user_id)
            ->where('is_saved_for_later', 0)
            ->where('store_id', $store_id)
            ->get();

        $sellerIds = [];

        foreach ($carts as $cart) {
            if ($cart->productVariant && $cart->productVariant->product && $cart->productVariant->product->sellerData) {
                $sellerIds[] = $cart->productVariant->product->sellerData->id;
            }

            if ($cart->comboProduct) {
                $sellerIds[] = $cart->comboProduct->seller_id;
            }
        }

        $uniqueSellerIds = array_values(array_unique(array_filter($sellerIds)));

        if (empty($uniqueSellerIds)) {
            return true;
        }

        $newSellerId = null;

        if ($product_type == 'regular') {
            $variant = Product_variants::with('product.sellerData')
                ->whereIn('id', $variantIds)
                ->first();

            $newSellerId = $variant?->product?->sellerData?->id;
        } else {
            $comboProduct = ComboProduct::whereIn('id', $variantIds)->first();
            $newSellerId = $comboProduct?->seller_id;
        }

        if (!empty($newSellerId)) {
            return in_array($newSellerId, $uniqueSellerIds);
        }

        return false;
    }
    public function isSingleProductType($product_variant_id, $user_id, $product_type, $store_id = '')
    {
        if (empty($product_variant_id) || empty($user_id)) {
            return false;
        }

        $variantIds = is_string($product_variant_id) && strpos($product_variant_id, ',') !== false
            ? explode(',', $product_variant_id)
            : (array) $product_variant_id;

        $productTypes = [];

        // 1️⃣ Get types from incoming product(s)
        if ($product_type == 'regular') {
            $productVariants = Product_variants::with('product')
                ->whereIn('id', $variantIds)
                ->get();

            foreach ($productVariants as $variant) {
                if ($variant->product) {
                    $productTypes[] = $variant->product->type;
                }
            }
        } else {
            $comboProducts = ComboProduct::whereIn('id', $variantIds)->get();
            foreach ($comboProducts as $combo) {
                $productTypes[] = $combo->product_type;
            }
        }

        // Flatten + clean types
        $productTypes = array_unique(array_filter($productTypes));

        $hasDigitalProduct = in_array('digital_product', $productTypes);
        $hasSimpleOrPhysical = array_intersect(['simple_product', 'variable_product', 'physical_product'], $productTypes);

        if ($hasDigitalProduct && !empty($hasSimpleOrPhysical)) {
            return false;
        }

        // 2️⃣ Get existing cart product types
        $carts = Cart::with(['productVariant.product', 'comboProduct'])
            ->where('user_id', $user_id)
            ->where('store_id', $store_id)
            ->where('is_saved_for_later', 0)
            ->get();

        $existingTypes = [];

        foreach ($carts as $cart) {
            if ($cart->productVariant && $cart->productVariant->product) {
                $existingTypes[] = $cart->productVariant->product->type;
            }
            if ($cart->comboProduct) {
                $existingTypes[] = $cart->comboProduct->product_type;
            }
        }

        $existingTypes = array_values(array_unique(array_filter($existingTypes)));

        // If no products in cart, allow
        if (empty($existingTypes)) {
            return true;
        }

        // 3️⃣ Get the new product type for comparison (assume first only)
        $newProductType = $productTypes[0] ?? null;

        if (!$newProductType) {
            return false;
        }

        // 4️⃣ Validate product type consistency
        if (in_array($newProductType, $existingTypes)) {
            return true;
        }

        if (
            !in_array('digital_product', $existingTypes) &&
            in_array($newProductType, ['simple_product', 'variable_product', 'physical_product'])
        ) {
            return true;
        }

        return false;
    }

    public function getCartCount($user_id, $store_id = '')
    {
        if (!empty($user_id)) {
            $count = Cart::where('user_id', $user_id)
                ->where('qty', '!=', 0)
                ->where('store_id', $store_id)
                ->where('is_saved_for_later', 0)
                ->distinct()
                ->count();
        } else {
            $count = 0;
        }
        return $count;
    }
    public function isVariantAvailableInCart($product_variant_id, $user_id)
    {
        // Use Eloquent to check if the variant is available in the cart\
        $cartItem = Cart::where('product_variant_id', $product_variant_id)
            ->where('user_id', $user_id)
            ->where('qty', '>', 0)
            ->where('is_saved_for_later', 0)
            ->select('id')
            ->first();


        return !is_null($cartItem);
    }
}