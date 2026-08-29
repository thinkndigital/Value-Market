<?php

namespace App\Services;
use App\Http\Controllers\Admin\AddressController;
use App\Libraries\Paystack;
use App\Libraries\Razorpay;
use App\Models\Address;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\CustomMessage;
use App\Models\Order;
use App\Models\OrderBankTransfers;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Parcelitem;
use App\Models\Product;
use App\Models\Promocode;
use App\Models\Product_variants;
use App\Models\ReturnRequest;
use App\Models\Seller;
use App\Models\Parcel;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFcm;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Request;
use App\Services\TranslationService;
use App\Services\FirebaseNotificationService;
use App\Services\MailService;
use App\Services\ProductService;
use App\Services\ComboProductService;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\SellerService;
use App\Services\MediaService;
use App\Services\ShiprocketService;
use App\Services\SettingService;
use App\Services\CurrencyService;
use App\Services\WalletService;
class OrderService
{
    public function placeOrder($data, $for_web = '', $language_code = '')
    {
        $store_id = isset($data['store_id']) && !empty($data['store_id']) ? $data['store_id'] : '';

        $product_variant_id = explode(',', $data['product_variant_id']);

        $cart_product_type = explode(',', $data['cart_product_type']);

        $quantity = explode(',', $data['quantity']);

        $check_current_stock_status = validateStock($product_variant_id, $quantity, $cart_product_type);

        if (isset($check_current_stock_status['error']) && $check_current_stock_status['error'] == true) {
            return json_encode($check_current_stock_status);
        }
        $total = 0;
        $promo_code_discount = 0;



        //fetch details from product_variants table for regular product

        $product_variant = Product_variants::select(
            'product_variants.*',
            'c.product_type as cart_product_type',
            DB::raw('(SELECT GROUP_CONCAT(tax.percentage) FROM taxes as tax WHERE FIND_IN_SET(tax.id, p.tax)) as tax_percentage'),
            DB::raw('(SELECT GROUP_CONCAT(tax.percentage) FROM taxes as tax WHERE FIND_IN_SET(tax.id, p.tax)) as tax_ids'),
            DB::raw('(SELECT GROUP_CONCAT(tax_name.title) FROM taxes as tax_name WHERE FIND_IN_SET(tax_name.id, p.tax)) as tax_name'),
            'p.seller_id',
            'p.name as product_name',
            'p.type as product_type',
            'p.is_prices_inclusive_tax',
            'p.download_link'
        )
            ->join('products as p', 'product_variants.product_id', '=', 'p.id')
            ->leftJoin('taxes as tax_id', function ($join) {
                $join->on(DB::raw('FIND_IN_SET(tax_id.id, p.tax)'), '>', DB::raw('0'));
            })
            ->leftJoin('cart as c', 'c.product_variant_id', '=', 'product_variants.id')
            ->whereIn('product_variants.id', $product_variant_id)
            ->where('c.product_type', 'regular')
            ->orderByRaw('FIELD(product_variants.id, ' . $data['product_variant_id'] . ')')
            ->get();

        // dd($product_variant->tosql(), $product_variant->getbindings());

        //fetch details from combo_products table for combo product

        $combo_product_variant = ComboProduct::select(
            'combo_products.*',
            'c.product_type as cart_product_type',
            DB::raw('(SELECT GROUP_CONCAT(c_tax.percentage) FROM taxes as c_tax WHERE FIND_IN_SET(c_tax.id, combo_products.tax)) as tax_percentage'),
            DB::raw('(SELECT GROUP_CONCAT(c_tax.percentage) FROM taxes as c_tax WHERE FIND_IN_SET(c_tax.id, combo_products.tax)) as tax_ids'),
            DB::raw('(SELECT GROUP_CONCAT(c_tax_title.title) FROM taxes as c_tax_title WHERE FIND_IN_SET(c_tax_title.id, combo_products.tax)) as tax_name'),
            'combo_products.seller_id',
            'combo_products.title as product_name',
            'combo_products.product_type',
            'combo_products.is_prices_inclusive_tax',
            'combo_products.download_link'
        )
            ->leftJoin('taxes as c_tax', function ($join) {
                $join->on(DB::raw('FIND_IN_SET(c_tax.id, combo_products.tax)'), '>', DB::raw('0'));
            })
            ->leftJoin('cart as c', 'c.product_variant_id', '=', 'combo_products.id')
            ->whereIn('combo_products.id', $product_variant_id)
            ->where('c.product_type', 'combo')
            ->orderByRaw('FIELD(combo_products.id, ' . $data['product_variant_id'] . ')')
            ->get();



        //merge both collection
        $product_variant = $product_variant->merge($combo_product_variant);
        // dd($product_variant);
        if (!empty($product_variant)) {

            $system_settings = app(SettingService::class)->getSettings('system_settings', true);
            $system_settings = json_decode($system_settings, true);

            $seller_ids = $product_variant->pluck('seller_id')->unique()->values()->all();

            if ($system_settings['single_seller_order_system'] == '1') {
                if (isset($seller_ids) && count($seller_ids) > 1) {
                    $response['error'] = true;
                    $response['message'] = 'Only one seller products are allow in one order.';
                    return $response;
                }
            }

            $delivery_charge = isset($data['delivery_charge']) && !empty($data['delivery_charge']) ? $data['delivery_charge'] : 0;
            $discount = isset($data['discount']) && !empty($data['discount']) ? $data['discount'] : 0;
            $gross_total = 0;
            $cart_data = [];

            for ($i = 0; $i < count($product_variant); $i++) {

                $pv_price[$i] = ($product_variant[$i]['special_price'] > 0 && $product_variant[$i]['special_price'] != null) ? $product_variant[$i]['special_price'] : $product_variant[$i]['price'];
                $tax_ids[$i] = (isset($product_variant[$i]['tax_ids']) && $product_variant[$i]['tax_ids'] != null) ? $product_variant[$i]['tax_ids'] : '0';
                $tax_percentage[$i] = (isset($product_variant[$i]['tax_percentage']) && $product_variant[$i]['tax_percentage'] != null) ? $product_variant[$i]['tax_percentage'] : '0';
                $tax_percntg[$i] = explode(',', $tax_percentage[$i]);
                $total_tax = array_sum($tax_percntg[$i]);
                if ((isset($product_variant[$i]['is_prices_inclusive_tax']) && $product_variant[$i]['is_prices_inclusive_tax'] == 0)) {
                    $tax_amount[$i] = $pv_price[$i] * ($total_tax / 100);
                    $pv_price[$i] = $pv_price[$i] + $tax_amount[$i];
                }

                $subtotal[$i] = ($pv_price[$i]) * $quantity[$i];
                $pro_name[$i] = $product_variant[$i]['product_name'];

                if ($product_variant[$i]['cart_product_type'] == 'regular') {
                    $variant_info = app(ProductService::class)->getVariantsValuesById($product_variant[$i]['id']);
                } else {
                    $variant_info = [];
                }

                $product_variant[$i]['variant_name'] = (isset($variant_info[0]['variant_values']) && !empty($variant_info[0]['variant_values'])) ? $variant_info[0]['variant_values'] : "";


                if ($tax_percentage[$i] != NUll && $tax_percentage[$i] > 0) {
                    $tax_amount[$i] = round($subtotal[$i] * $total_tax / 100, 2);
                } else {
                    $tax_amount[$i] = 0;
                    $tax_percentage[$i] = 0;
                }
                $gross_total += $subtotal[$i];
                $total += $subtotal[$i];
                $total = round($total, 2);
                $gross_total = round($gross_total, 2);
                if ($product_variant[$i]->cart_product_type == 'regular') {
                    $product_name = app(TranslationService::class)->getDynamicTranslation(
                        Product::class,
                        'name',
                        $product_variant[$i]->product_id,
                        $language_code
                    );
                } else {
                    $product_name = app(TranslationService::class)->getDynamicTranslation(
                        ComboProduct::class,
                        'title',
                        $product_variant[$i]->product_id,
                        $language_code
                    );
                }
                array_push(
                    $cart_data,
                    array(
                        'name' => $product_name,
                        'tax_amount' => $tax_amount[$i],
                        'qty' => $quantity[$i],
                        'sub_total' => $subtotal[$i],
                    )
                );
            }


            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $app_name = isset($settings['app_name']) && !empty($settings['app_name']) ? $settings['app_name'] : '';

            $currency = isset($settings['currency']) && !empty($settings['currency']) ? $settings['currency'] : '';
            if (isset($settings['minimum_cart_amount']) && !empty($settings['minimum_cart_amount'])) {
                $carttotal = $total + $delivery_charge;
                // dd($total);
                if ($carttotal < $settings['minimum_cart_amount']) {
                    $response = [
                        'error' => true,
                        'message' => 'Total amount should be greater or equal to ' . $currency . $settings['minimum_cart_amount'] . ' total is ' . $currency . $carttotal,
                        'code' => 102,
                    ];
                    return $response;
                }
            }


            // add promocode calculation here
            if (isset($data['promo_code_id']) && !empty($data['promo_code_id'])) {
                $data['promo_code'] = fetchDetails(Promocode::class, ['id' => $data['promo_code_id']], 'promo_code')[0]->promo_code;
                // dd($total);
                $promo_code = app(abstract: PromoCodeService::class)->validatePromoCode($data['promo_code_id'], $data['user_id'], $total, 1);
                $promo_code = $promo_code->original;
                if ($promo_code['error'] == false) {

                    if ($promo_code['data'][0]->discount_type == 'percentage') {
                        $promo_code_discount = (isset($promo_code['data'][0]->is_cashback) && $promo_code['data'][0]->is_cashback == 0) ? floatval($total * $promo_code['data'][0]->discount / 100) : 0;
                    } else {
                        $promo_code_discount = (isset($promo_code['data'][0]->is_cashback) && $promo_code['data'][0]->is_cashback == 0) ? $promo_code['data'][0]->discount : 0;
                    }
                    if ($promo_code_discount <= $promo_code['data'][0]->max_discount_amount) {
                        $total = (isset($promo_code['data'][0]->is_cashback) && $promo_code['data'][0]->is_cashback == 0) ? floatval($total) - $promo_code_discount : floatval($total);
                    } else {
                        $total = (isset($promo_code['data'][0]->is_cashback) && $promo_code['data'][0]->is_cashback == 0) ? floatval($total) - $promo_code['data'][0]->max_discount_amount : floatval($total);
                        $promo_code_discount = $promo_code['data'][0]->max_discount_amount;
                    }
                } else {
                    return $promo_code;
                }
            }
            // ---------------------------------------------------------

            //add create parcel seller wise code here

            $parcels = array();
            for ($i = 0; $i < count($product_variant_id); $i++) {
                // dd($product_variant[$i]);
                $product_variant[$i]['qty'] = $quantity[$i];
            }

            foreach ($product_variant as $product) {

                $prctg = (isset($product['tax_percentage']) && $product['tax_percentage'] != null) ? $product['tax_percentage'] : '0';
                if ((isset($product['is_prices_inclusive_tax']) && $product['is_prices_inclusive_tax'] == 0)) {
                    $tax_percentage = explode(',', $prctg);
                    $total_tax = array_sum($tax_percentage);

                    $price_tax_amount = $product['price'] * ($total_tax / 100);
                    $special_price_tax_amount = $product['special_price'] * ($total_tax / 100);
                } else {
                    $price_tax_amount = 0;
                    $special_price_tax_amount = 0;
                }

                if (floatval($product['special_price']) > 0) {
                    $product['total'] = floatval($product['special_price'] + $special_price_tax_amount) * $product['qty'];
                } else {
                    $product['total'] = floatval($product['price'] + $price_tax_amount) * $product['qty'];
                }
                if (isset($parcels[$product['seller_id']]['variant_id']) && !empty($product['id'])) {
                    $parcels[$product['seller_id']]['variant_id'] .= $product['id'] . ',';
                } elseif (!empty($product['id'])) {
                    $parcels[$product['seller_id']]['variant_id'] = $product['id'] . ',';
                }
                if (isset($parcels[$product['seller_id']]['total']) && !empty($product['total'])) {
                    $parcels[$product['seller_id']]['total'] += $product['total'];
                } elseif (!empty($product['total'])) {
                    $parcels[$product['seller_id']]['total'] = $product['total'];
                }
            }
            $parcel_sub_total = 0.0;
            // dd($parcels);
            foreach ($parcels as $seller_id => $parcel) {
                $parcel_sub_total += $parcel['total'];
            }
            // ---------------------------------------------------------

            // $final_total = $total + intval($delivery_charge) - $discount;
            // $final_total = $total + intval($delivery_charge) - $promo_code_discount;
            $final_total = $total + intval($delivery_charge);
            $final_total = round($final_total, 2);

            $total_payable = $final_total;
            // dd($final_total);

            // Phase 1 (docs/PHASE_1_TRANSACTION_BOUNDARIES.md): wallet debit, order/order_items/order_charges
            // creation, and stock decrement below are one atomic unit - previously none of this was wrapped
            // in a DB transaction, so a failure partway through (e.g. order creation failing after the
            // wallet was already debited) could silently leave the customer's wallet debited with no order
            // to show for it. Notifications/emails after the commit() are intentionally left outside the
            // transaction: they are external side effects, not database writes, and should not hold a DB
            // transaction open across a network call.
            DB::beginTransaction();

            try {
                if ($data['is_wallet_used'] == '1' && $data['wallet_balance_used'] <= $final_total) {

                    $wallet_balance = app(WalletService::class)->updateWalletBalance('debit', $data['user_id'], $data['wallet_balance_used'], "Used against Order Placement");
                    if ($wallet_balance['error'] == false) {
                        $total_payable -= $data['wallet_balance_used'];
                        $Wallet_used = true;
                    } else {
                        DB::rollBack();
                        $response['error'] = true;
                        $response['message'] = $wallet_balance['error_message'];
                        return $response;
                    }
                } else {
                    if ($data['is_wallet_used'] == 1) {
                        DB::rollBack();
                        $response['error'] = true;
                        $response['message'] = 'Wallet Balance should not exceed the total amount';
                        return $response;
                    }
                }


            // $status = (isset($data['payment_method'])) && (strtolower($data['payment_method']) == 'cod' || $data['payment_method'] == 'paystack' || $data['payment_method'] == 'stripe' || $data['payment_method'] == 'razorpay') ? 'received' : 'awaiting';
            $status = ((isset($data['status'])) && !empty($data['status'])) ? $data['status'] : 'awaiting';
            if (isset($data['wallet_balance_used']) && $data['wallet_balance_used'] == $final_total) {
                $status = 'received';
            }
            if ((isset($data['payment_method'])) && (strtolower($data['payment_method']) == 'cod')) {
                $status = 'received';
            }
            if ($data['is_wallet_used'] == '1') {
                $data['payment_method'] = 'wallet';
            }
            // dd($status);
            $order_payment_currency_data = fetchDetails(Currency::class, ['code' => $data['order_payment_currency_code']], ['id', 'exchange_rate']);
            $base_currency = app(CurrencyService::class)->getDefaultCurrency()->code;
            $order_data = [
                'user_id' => $data['user_id'],
                'mobile' => (isset($data['mobile']) && !empty($data['mobile']) && $data['mobile'] != '' && $data['mobile'] != 'NULL') ? $data['mobile'] : '',
                'total' => $gross_total,
                'promo_discount' => (isset($promo_code_discount) && $promo_code_discount != NULL) ? $promo_code_discount : '0',
                'total_payable' => $total_payable,
                'delivery_charge' => intval($delivery_charge),
                'is_delivery_charge_returnable' => isset($data['is_delivery_charge_returnable']) ? $data['is_delivery_charge_returnable'] : 0,
                'wallet_balance' => (isset($Wallet_used) && $Wallet_used == true) ? $data['wallet_balance_used'] : '0',
                'final_total' => $final_total,
                'discount' => $discount,
                'payment_method' => $data['payment_method'] ?? '',
                'promo_code_id' => (isset($data['promo_code_id'])) ? $data['promo_code_id'] : ' ',
                'email' => isset($data['email']) ? $data['email'] : ' ',
                'is_pos_order' => isset($data['is_pos_order']) ? $data['is_pos_order'] : 0,
                // Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): the order-origin discriminator, derived from the
                // same flag already passed in - single source of truth, no new parameter needed at either
                // call site (Seller\PosController or the storefront checkout). is_pos_order itself is
                // unchanged; channel is an additive, richer parallel concept. Phase 7
                // (docs/PHASE_7_AFFILIATE_ENGINE.md) added the affiliate_code branch - CHANNEL_AFFILIATE was
                // reserved for this back in Phase 3, unused until now.
                'channel' => !empty($data['is_pos_order'])
                    ? Order::CHANNEL_POS
                    : (!empty($data['affiliate_code']) ? Order::CHANNEL_AFFILIATE : Order::CHANNEL_MARKETPLACE),
                'is_shiprocket_order' => isset($data['delivery_type']) && !empty($data['delivery_type']) && $data['delivery_type'] == 'standard_shipping' ? 1 : 0,
                'order_payment_currency_id' => !$order_payment_currency_data->isEmpty() ? $order_payment_currency_data[0]->id : '',
                'order_payment_currency_code' => $data['order_payment_currency_code'] ?? "",
                'order_payment_currency_conversion_rate' => !$order_payment_currency_data->isEmpty() ? $order_payment_currency_data[0]->exchange_rate : '',
                'base_currency_code' => $base_currency,
            ];

            if (isset($data['address_id']) && !empty($data['address_id'])) {
                $order_data['address_id'] = (isset($data['address_id']) ? $data['address_id'] : '');
            }

            if (isset($data['delivery_date']) && !empty($data['delivery_date']) && !empty($data['delivery_time']) && isset($data['delivery_time'])) {
                $order_data['delivery_date'] = date('Y-m-d', strtotime($data['delivery_date']));
                $order_data['delivery_time'] = $data['delivery_time'];
            }
            $addressController = app(AddressController::class);
            if (isset($data['address_id']) && !empty($data['address_id'])) {

                $address_data = $addressController->getAddress(null, $data['address_id'], true);

                if (!empty($address_data)) {
                    $order_data['latitude'] = $address_data[0]->latitude;
                    $order_data['longitude'] = $address_data[0]->longitude;
                    $order_data['address'] = (!empty($address_data[0]->address) && $address_data[0]->address != 'NULL') ? $address_data[0]->address . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->landmark) && $address_data[0]->landmark != 'NULL') ? $address_data[0]->landmark . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->area) && $address_data[0]->area != 'NULL') ? $address_data[0]->area . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->city) && $address_data[0]->city != 'NULL') ? $address_data[0]->city . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->state) && $address_data[0]->state != 'NULL') ? $address_data[0]->state . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->country) && $address_data[0]->country != 'NULL') ? $address_data[0]->country . ', ' : '';
                    $order_data['address'] .= (!empty($address_data[0]->pincode) && $address_data[0]->pincode != 'NULL') ? $address_data[0]->pincode : '';
                }
            } else {
                $order_data['address'] = "";
            }

            if (!empty($data['latitude']) && !empty($data['longitude'])) {
                $order_data['latitude'] = $data['latitude'];
                $order_data['longitude'] = $data['longitude'];
            }
            $order_data['notes'] = isset($data['order_note']) ? $data['order_note'] : '';
            $order_data['store_id'] = $store_id;

            $order = Order::forceCreate($order_data);

            $order_id = $order->id;

            // Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): attributes this order to an affiliate link when
            // the storefront checkout was reached via one - recorded 'pending', not paid yet (see
            // AffiliateService::approveConversionsForOrder(), called on delivery, same pattern the existing
            // refer-a-friend bonus already uses). A missing/invalid code, or no commission rule configured
            // anywhere, means no conversion is recorded - the sale itself is never blocked by this.
            if (!empty($data['affiliate_code'])) {
                app(\App\Services\AffiliateService::class)->recordConversion(
                    $data['affiliate_code'],
                    $order_id,
                    $data['user_id'] ?? null,
                    (float) $order_data['final_total']
                );
            }

            for ($i = 0; $i < count($product_variant); $i++) {
                // dd($product_variant[$i]);
                if ($product_variant[$i]->cart_product_type == 'regular') {
                    $product_name = app(TranslationService::class)->getDynamicTranslation(
                        Product::class,
                        'name',
                        $product_variant[$i]->product_id,
                        $language_code
                    );
                } else {
                    $product_name = app(TranslationService::class)->getDynamicTranslation(
                        ComboProduct::class,
                        'title',
                        $product_variant[$i]->product_id,
                        $language_code
                    );
                }
                $product_variant_data[$i] = [
                    'user_id' => $data['user_id'],
                    'order_id' => $order_id,
                    'seller_id' => $product_variant[$i]['seller_id'],
                    'product_name' => $product_name,
                    // 'product_name' =>  $product_variant[$i]['product_name'],
                    'variant_name' => $product_variant[$i]['variant_name'],
                    'product_variant_id' => $product_variant[$i]['id'],
                    'quantity' => $quantity[$i],
                    'price' => $pv_price[$i],
                    'tax_percent' => $total_tax,
                    'tax_ids' => $tax_ids[$i],
                    'tax_amount' => $tax_amount[$i],
                    'sub_total' => $subtotal[$i],
                    'status' => json_encode(array(array($status, date("d-m-Y h:i:sa")))),
                    'active_status' => $status,
                    'otp' => 0,
                    'store_id' => $store_id,
                    'order_type' => $product_variant[$i]['cart_product_type'] . "_order",
                    'attachment' => $data['attachment_path'][$product_variant[$i]['id']] ?? "",
                ];
                // dd($product_variant_data[$i]);
                $order_items = OrderItems::forceCreate($product_variant_data[$i]);

                $order_item_id = $order_items->id;

                if (isset($product_variant[$i]['download_link']) && !empty($product_variant[$i]['download_link'])) {
                    $hash_link = $product_variant[$i]['download_link'] . '?' . $order_item_id;
                    $hash_link_data = ['hash_link' => $hash_link];
                    OrderItems::where('id', $order_item_id)->update($hash_link_data);
                }
            }
            // add here  order_charges_parcel and insert in table
            $discount_percentage = 0.00;

            foreach ($parcels as $seller_id => $parcel) {
                $parcel['delivery_charge'] = 0;

                $discount_percentage = ($parcel['total'] * 100) / $parcel_sub_total;
                $seller_promocode_discount = ($promo_code_discount * $discount_percentage) / 100;
                $seller_delivery_charge = ($delivery_charge * $discount_percentage) / 100;
                $otp = mt_rand(100000, 999999);
                $order_item_ids = '';
                $varient_ids = explode(',', trim($parcel['variant_id'], ','));
                $parcel_total = $parcel['total'] + intval($parcel['delivery_charge']) - $seller_promocode_discount;
                $parcel_total = round($parcel_total, 2);
                foreach ($varient_ids as $ids) {
                    $order_item_ids .= fetchDetails(OrderItems::class, ['seller_id' => $seller_id, 'product_variant_id' => $ids, 'order_id' => $order_id], 'id')[0]->id . ',';
                }
                $order_item_id = explode(',', trim($order_item_ids, ','));
                foreach ($order_item_id as $ids) {
                    updateDetails(['otp' => $otp], ['id' => $ids], OrderItems::class);
                }

                $order_parcels = [
                    'seller_id' => $seller_id,
                    'product_variant_ids' => trim($parcel['variant_id'], ','),
                    'order_id' => $order_id,
                    'order_item_ids' => trim($order_item_ids, ','),
                    'delivery_charge' => round($seller_delivery_charge, 2),
                    'promo_code_id' => $data['promo_code_id'] ?? '',
                    'promo_discount' => round($seller_promocode_discount, 2),
                    'sub_total' => $parcel['total'],
                    'total' => $parcel_total,
                    'otp' => ($system_settings['order_delivery_otp_system'] == '1') ? $otp : 0,
                ];


                $order_charges = OrderCharges::forceCreate($order_parcels);
            }

            $product_variant_ids = explode(',', $data['product_variant_id']);

            $qtns = explode(',', $data['quantity'] ?? '');

            for ($i = 0; $i < count($product_variant_ids); $i++) {

                if ($cart_product_type[$i] == 'regular') {
                    app(ProductService::class)->updateStock($product_variant_ids[$i], $qtns[$i], '');
                } else {
                    app(ComboProductService::class)->updateComboStock($product_variant_ids[$i], $qtns[$i], '');
                }
            }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('placeOrder failed, transaction rolled back: ' . $e->getMessage(), ['exception' => $e]);
                $response = [
                    'error' => true,
                    'message' => 'Order could not be placed. Please try again.',
                ];
                return ($for_web == 1) ? $response : response()->json($response);
            }



            $overall_total = array(
                'total_amount' => array_sum($subtotal),
                'delivery_charge' => $delivery_charge,
                'discount' => $discount,
                'tax_amount' => array_sum($tax_amount),
                // 'tax_percentage' => array_sum($tax_percentage),
                'tax_percentage' => array_sum(array_map('floatval', $tax_percentage)),
                // 'discount' => $order_data['promo_discount'],
                'wallet' => $order_data['wallet_balance'],
                'final_total' => $order_data['final_total'],
                'total_payable' => $order_data['total_payable'],
                'address' => (isset($order_data['address'])) ? $order_data['address'] : '',
                'payment_method' => $data['payment_method'] ?? ''
            );

            // add send notification,custom notificationa nd send mail code here

            $user_res = fetchDetails(User::class, ['id' => $data['user_id']], ['username', 'fcm_id', 'email']);
            $custom_notification = fetchDetails(CustomMessage::class, ['type' => "place_order"], '*');
            $hashtag_customer_name = '< customer_name >';
            $hashtag_order_id = '< order_item_id >';
            $hashtag_application_name = '< application_name >';
            $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
            $hashtag = html_entity_decode($string);
            $notification_data = str_replace(array($hashtag_customer_name, $hashtag_order_id, $hashtag_application_name), array($user_res[0]->username, $order_id, $app_name), $hashtag);
            $message = outputEscaping(trim($notification_data, '"'));
            $title = "New order placed ID # " . $order_id;
            $customer_msg = !$custom_notification->isEmpty() ? $message : 'New order received for  ' . $app_name . ' please process it.';
            $fcm_ids = array();
            $seller_fcm_ids = array();
            foreach ($parcels as $seller_id => $parcel) {
                $seller_id = Seller::where('id', $seller_id)->value('user_id');
                $fcmMsg = array(
                    'title' => "$title",
                    'body' => "$customer_msg",
                    'type' => "order",
                    'order_id' => "$order_id",
                    'store_id' => "$store_id",
                );

                $results = UserFcm::with('user:id,id,is_notification_on')
                    ->where('user_id', $seller_id)
                    ->whereHas('user', function ($q) {
                        $q->where('is_notification_on', 1);
                    })
                    ->get()
                    ->map(function ($fcm) {
                        return [
                            'fcm_id' => $fcm->fcm_id,
                            'is_notification_on' => $fcm->user?->is_notification_on,
                        ];
                    });
                $seller_fcm_ids = [];
                // dd($results);
                foreach ($results as $result) {
                    $seller_fcm_ids[] = $result['fcm_id'];
                }
                $registrationIDs_chunks = array_chunk($seller_fcm_ids, 1000);
                app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
            }
            $notification_store_id = $order->store_id;
            // dd($store_id);
            $fcmMsg = array(
                'title' => "$title",
                'body' => "$customer_msg",
                'type' => "order",
                'order_id' => "$order_id",
                'store_id' => "$store_id",
            );

            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $data['user_id'])
                ->whereHas('user', function ($q) {
                    $q->where('is_notification_on', 1);
                })
                ->get()
                ->map(function ($fcm) {
                    return [
                        'fcm_id' => $fcm->fcm_id,
                        'is_notification_on' => $fcm->user?->is_notification_on,
                    ];
                });
            $fcm_ids = [];
            foreach ($results as $result) {
                $fcm_ids[] = $result['fcm_id'];
            }

            $fcmMsg = array(
                'title' => "$title",
                'body' => "$customer_msg",
                'type' => "order",
                'order_id' => "$order_id",
                'store_id' => "$store_id",
            );
            $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
            if ($status !== 'awaiting' && $status !== 'Awaiting') {
                app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);
                $userEmail = $user_res[0]->email;

                // Changelog v1.0.3 ("Email order invoices") + v1.0.10 ("Queue integration" / "Faster order
                // processing"): this used to link to /admin/orders/generat_invoice_PDF/{id} - an admin-only
                // route, unreachable by the customer this email is sent to (they have no admin session) -
                // and generated the PDF + sent the mail inline, inside the checkout request itself. Both
                // fixed: the PDF is attached directly (no authenticated link needed), and the actual
                // generation/send work is now deferred to a queued job (SendOrderConfirmationEmailJob) so it
                // never blocks the customer's checkout response. A missing customer email or email not yet
                // configured is still checked here (cheap, in-request) so nothing is queued needlessly; any
                // failure inside the job itself (PDF generation, SMTP) is caught by the job's own `failed()`
                // handler, never propagating back to affect order placement, which already committed above.
                if (!empty($userEmail) && app(MailService::class)->isEmailConfigured()) {
                    dispatch(new \App\Jobs\SendOrderConfirmationEmailJob($order_id));
                }
            }

            app(CartService::class)->removeFromCart($data);
            foreach ($product_variant_data as &$order_item_data) {
                $order_item_data['attachment'] = asset('/storage/' . $order_item_data['attachment']);
            }
            // dd($product_variant_data);
            $user_balance = fetchDetails(User::class, ['id' => $data['user_id']], 'balance');
            $response = [
                'error' => false,
                'message' => 'Order Placed Successfully',
                'order_id' => $order_id,
                'final_total' => ($data['is_wallet_used'] == '1') ? $final_total -= $data['wallet_balance_used'] : $final_total,
                'total_payable' => $total_payable,
                'order_item_data' => $product_variant_data,
                'balance' => $user_balance,
            ];
            if ($for_web == 1) {
                return $response;
            } else {
                return response()->json($response);
            }
        } else {
            $user_balance = fetchDetails(User::class, ['id' => $data['user_id']], 'balance');
            $response = [
                'error' => true,
                'message' => "Product(s) Not Found!",
                'balance' => $user_balance,
            ];

            return response()->json($response);
        }
    }
    public function fetchOrders($order_id = NULL, $user_id = NULL, $status = NULL, $delivery_boy_id = NULL, $limit = NULL, $offset = NULL, $sort = 'o.id', $order = 'DESC', $download_invoice = false, $start_date = null, $end_date = null, $search = null, $city_id = null, $area_id = null, $seller_id = null, $order_type = '', $from_seller = false, $store_id = null, $language_code = "")
    {

        $total_query = DB::table('orders as o')
            ->select(DB::raw('COUNT(DISTINCT o.id) as total'), 'oi.order_type')
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('order_items as oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
            ->leftJoin('order_trackings as ot', 'ot.order_item_id', '=', 'oi.id')
            ->leftJoin('addresses as a', 'a.id', '=', 'o.address_id')
            ->leftJoin('combo_products as cp', 'cp.id', '=', 'oi.product_variant_id')
            ->where(function ($query) {
                $query->where('oi.order_type', 'regular_order')
                    ->orWhere('oi.order_type', 'combo_order');
            });
        if (isset($store_id) && $store_id !== NULL && !empty($store_id)) {
            $total_query->where('o.store_id', $store_id);
        }

        if (isset($order_id) && $order_id !== NULL && !empty($order_id)) {
            $total_query->where('o.id', $order_id);
        }

        if (isset($delivery_boy_id) && $delivery_boy_id !== null && !empty($delivery_boy_id)) {
            $total_query->where('oi.delivery_boy_id', $delivery_boy_id);
        }

        if (isset($user_id) && $user_id !== null && !empty($user_id)) {
            $total_query->where('o.user_id', $user_id);
        }

        if (isset($city_id) && $city_id !== null && !empty($city_id)) {
            $total_query->where('a.city_id', $city_id);
        }

        if (isset($area_id) && $area_id !== null && !empty($area_id)) {
            $total_query->where('a.area_id', $area_id);
        }

        if (isset($seller_id) && $seller_id !== null && !empty($seller_id)) {
            $total_query->where('oi.seller_id', $seller_id);
        }

        if (isset($order_type) && $order_type !== '' && $order_type === 'digital') {
            $total_query->where(function ($query) {
                $query->where('p.type', 'digital_product')
                    ->orWhere('cp.product_type', 'digital_product');
            });
        }



        if (isset($order_type) && $order_type !== '' && $order_type === 'simple') {

            $total_query->where(function ($query) {
                $query->where('p.type', '!=', 'digital_product')
                    ->orWhere('cp.product_type', '!=', 'digital_product');
            });
        }
        if (isset($status) && !empty($status) && $status != '' && is_array($status) && count($status) > 0) {
            $status = array_map('trim', $status);

            $total_query->whereIn('oi.active_status', $status);
        }

        if (isset($start_date) && $start_date !== null && isset($end_date) && $end_date !== null && !empty($end_date) && !empty($start_date)) {
            $total_query->whereDate('o.created_at', '>=', $start_date)
                ->whereDate('o.created_at', '<=', $end_date);
        }

        if (!empty($start_date)) {
            $total_query->whereDate('o.created_at', '>=', $start_date);
        }

        if (!empty($end_date)) {
            $total_query->whereDate('o.created_at', '<=', $end_date);
        }
        if (isset($search) && $search !== null && !empty($search)) {
            $filters = [
                'u.username' => $search,
                'u.email' => $search,
                'o.id' => $search,
                'o.mobile' => $search,
                'o.address' => $search,
                'o.payment_method' => $search,
                'o.delivery_time' => $search,
                'o.created_at' => $search,
                'oi.active_status' => $search,
                'p.name' => $search,
            ];

            $total_query->where(function ($query) use ($filters) {
                foreach ($filters as $column => $value) {
                    $query->orWhere($column, 'LIKE', '%' . $value . '%');
                }
            });
        }
        if (isset($search) && $search !== null && !empty($search)) {
            $combo_filters = [
                'u.username' => $search,
                'u.email' => $search,
                'o.id' => $search,
                'o.mobile' => $search,
                'o.address' => $search,
                'o.payment_method' => $search,
                'o.delivery_time' => $search,
                'o.created_at' => $search,
                'oi.active_status' => $search,
                'cp.title' => $search,
            ];

            $total_query->where(function ($query) use ($filters) {
                foreach ($filters as $column => $value) {
                    $query->orWhere($column, 'LIKE', '%' . $value . '%');
                }
            });
        }

        if (isset($seller_id) && $seller_id !== null) {
            $total_query->where('oi.active_status', '!=', 'awaiting');
        }
        $total_query->where('o.is_pos_order', 0);
        if ($sort === 'created_at') {
            $sort = 'o.created_at';
        }

        $total_query->orderBy($sort, $order);

        $orderCount = $total_query->get()->toArray();
        $total = "0";
        foreach ($orderCount as $row) {

            $total = $row->total;
        }
        if (empty($sort)) {
            $sort = 'o.created_at';
        }

        $regularOrderSearchRes = DB::table('orders AS o')
            ->select(
                'o.*',
                'u.username',
                'u.image as user_profile_image',
                'u.country_code',
                'p.name',
                'p.type',
                'p.id as product_id',
                'p.slug',
                'p.download_allowed',
                'p.pickup_location',
                'a.name AS order_recipient_person',
                'pv.special_price',
                'pv.price',
                'oc.delivery_charge AS seller_delivery_charge',
                'oc.promo_discount AS seller_promo_discount',
                'oi.order_type',
                'sd.user_id as main_seller_id',
            )
            ->leftJoin('users AS u', 'u.id', '=', 'o.user_id')
            ->leftJoin('order_items AS oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('seller_data AS sd', 'sd.id', '=', 'oi.seller_id')
            ->leftJoin('product_variants AS pv', 'pv.id', '=', 'oi.product_variant_id')
            ->leftJoin('addresses AS a', 'a.id', '=', 'o.address_id')
            ->leftJoin('order_charges AS oc', 'o.id', '=', 'oc.order_id')
            ->leftJoin('products AS p', 'pv.product_id', '=', 'p.id');

        if (isset($store_id) && $store_id != null) {
            $regularOrderSearchRes->where('o.store_id', $store_id);
        }

        if (isset($order_id) && $order_id != null) {
            $regularOrderSearchRes->where('o.id', $order_id);
        }

        if (isset($user_id) && $user_id != null) {
            $regularOrderSearchRes->where('o.user_id', $user_id);
        }

        if (isset($delivery_boy_id) && $delivery_boy_id != null) {
            $regularOrderSearchRes->where('oi.delivery_boy_id', $delivery_boy_id);
        }

        if (isset($seller_id) && $seller_id != null) {
            $regularOrderSearchRes->where(function ($query) use ($seller_id) {
                $query->where('oi.seller_id', $seller_id)
                    ->orWhere('oc.seller_id', $seller_id);
            });
        }

        if (isset($start_date) && $start_date != null && isset($end_date) && $end_date != null) {
            $regularOrderSearchRes->whereDate('o.created_at', '>=', $start_date)
                ->whereDate('o.created_at', '<=', $end_date);
        }

        if (!empty($start_date)) {
            $regularOrderSearchRes->whereDate('o.created_at', '>=', $start_date);
        }

        if (!empty($end_date)) {
            $regularOrderSearchRes->whereDate('o.created_at', '<=', $end_date);
        }

        if (isset($order_type) && $order_type != '' && $order_type == 'digital') {

            $regularOrderSearchRes->where('p.type', 'digital_product');
        }

        if (isset($order_type) && $order_type != '' && $order_type == 'simple') {
            $regularOrderSearchRes->where('p.type', '!=', 'digital_product');
        }

        if (isset($status) && !empty($status) && $status != '' && is_array($status) && count($status) > 0) {
            $status = array_map('trim', $status);
            $regularOrderSearchRes->whereIn('oi.active_status', $status);
        }

        if (isset($filters) && !empty($filters)) {
            $regularOrderSearchRes->where(function ($query) use ($filters) {
                foreach ($filters as $column => $value) {
                    $query->orWhere($column, 'LIKE', '%' . $value . '%');
                }
            });
        }
        $regularOrderSearchRes->where('o.is_pos_order', 0);
        $regularOrderSearchRes->groupBy('o.id');
        $regularOrderSearchRes->orderBy($sort, $order);
        $regularOrderSearchRes = $regularOrderSearchRes->get();
        $comboOrderSearchRes = DB::table('orders AS o')
            ->select(
                'o.*',
                'u.username',
                'u.image as user_profile_image',
                'u.country_code',
                'a.name AS order_recipient_person',
                'oc.delivery_charge AS seller_delivery_charge',
                'oc.promo_discount AS seller_promo_discount',
                'cp.title as name',
                'cp.id as product_id',
                'cp.product_type as type',
                'cp.download_allowed',
                'cp.pickup_location',
                'cp.special_price',
                'cp.price',
                'cp.slug',
                'oi.order_type',
                'sd.user_id as main_seller_id',
            )
            ->leftJoin('users AS u', 'u.id', '=', 'o.user_id')
            ->leftJoin('order_items AS oi', 'o.id', '=', 'oi.order_id')
            ->leftJoin('seller_data AS sd', 'sd.id', '=', 'oi.seller_id')
            ->leftJoin('combo_products as cp', 'cp.id', '=', 'oi.product_variant_id')
            ->leftJoin('addresses AS a', 'a.id', '=', 'o.address_id')
            ->leftJoin('order_charges AS oc', 'o.id', '=', 'oc.order_id');

        if (isset($store_id) && $store_id != null) {
            $comboOrderSearchRes->where('o.store_id', $store_id);
        }

        if (isset($order_id) && $order_id != null) {
            $comboOrderSearchRes->where('o.id', $order_id);
        }

        if (isset($user_id) && $user_id != null) {
            $comboOrderSearchRes->where('o.user_id', $user_id);
        }

        if (isset($delivery_boy_id) && $delivery_boy_id != null) {
            $comboOrderSearchRes->where('oi.delivery_boy_id', $delivery_boy_id);
        }

        if (isset($seller_id) && $seller_id != null) {
            $comboOrderSearchRes->where(function ($query) use ($seller_id) {
                $query->where('oi.seller_id', $seller_id)
                    ->orWhere('oc.seller_id', $seller_id);
            });
        }

        if (isset($start_date) && $start_date != null && isset($end_date) && $end_date != null) {
            $comboOrderSearchRes->whereDate('o.created_at', '>=', $start_date)
                ->whereDate('o.created_at', '<=', $end_date);
        }


        if (!empty($start_date)) {
            $comboOrderSearchRes->whereDate('o.created_at', '>=', $start_date);
        }

        if (!empty($end_date)) {
            $comboOrderSearchRes->whereDate('o.created_at', '<=', $end_date);
        }


        if (isset($order_type) && $order_type != '' && $order_type == 'digital') {
            $comboOrderSearchRes->where('cp.product_type', 'digital_product');
        }

        if (isset($order_type) && $order_type != '' && $order_type == 'simple') {
            $comboOrderSearchRes->where('cp.product_type', '!=', 'digital_product');
        }

        if (isset($status) && !empty($status) && $status != '' && is_array($status) && count($status) > 0) {
            $status = array_map('trim', $status);
            $comboOrderSearchRes->whereIn('oi.active_status', $status);
        }

        if (isset($combo_filters) && !empty($combo_filters)) {
            $comboOrderSearchRes->where(function ($query) use ($combo_filters) {
                foreach ($combo_filters as $column => $value) {
                    $query->orWhere($column, 'LIKE', '%' . $value . '%');
                }
            });
        }
        $comboOrderSearchRes->where('o.is_pos_order', 0);
        $comboOrderSearchRes->groupBy('o.id');
        $comboOrderSearchRes->orderBy($sort, $order);


        $comboOrderSearchRes = $comboOrderSearchRes->get();


        $searchRes = $regularOrderSearchRes->merge($comboOrderSearchRes)->unique('id');

        $searchRes = $searchRes->sortBy($sort);
        // Applying limit and offset
        if ($limit != null || $offset != null) {
            $searchRes = $searchRes->slice($offset)->take($limit);
        }

        // Convert the sorted and sliced collection back to array
        $orderDetails = $searchRes->values()->all();
        for ($i = 0; $i < count($orderDetails); $i++) {
            $prCondition = ($user_id != NULL && !empty(trim($user_id)) && is_numeric($user_id))
                ? " pr.user_id = $user_id "
                : "";

            $crCondition = ($user_id != NULL && !empty(trim($user_id)) && is_numeric($user_id))
                ? " cr.user_id = $user_id "
                : "";
            $regularOrderItemData = DB::table('order_items AS oi')
                ->select(
                    'oi.*',
                    'p.id AS product_id',
                    'p.is_cancelable',
                    'p.is_attachment_required',
                    'p.is_prices_inclusive_tax',
                    'p.cancelable_till',
                    'p.type AS product_type',
                    'p.slug',
                    'p.download_allowed',
                    'p.download_link',
                    'ss.store_name',
                    'u.longitude AS seller_longitude',
                    'u.mobile AS seller_mobile',
                    'u.address AS seller_address',
                    'u.latitude AS seller_latitude',
                    DB::raw('(SELECT username FROM users WHERE id = oi.delivery_boy_id) AS delivery_boy_name'),
                    'ss.store_description',
                    'ss.rating AS seller_rating',
                    'ss.logo AS seller_profile',
                    'ot.courier_agency',
                    'ot.tracking_id',
                    'ot.awb_code',
                    'ot.url',
                    DB::raw('(SELECT username FROM users WHERE id = ' . (!empty($orderDetails[$i]->main_seller_id) ? $orderDetails[$i]->main_seller_id : '0') . ') AS seller_name'),
                    'p.is_returnable',
                    'pv.special_price',
                    'pv.price AS main_price',
                    'p.image',
                    'p.name AS product_name',
                    'p.pickup_location',
                    'pv.weight',
                    'p.rating AS product_rating',
                    'pr.rating AS user_rating',
                    'pr.images AS user_rating_images',
                    'pr.title AS user_rating_title',
                    'pr.comment AS user_rating_comment',
                    'oi.status AS status',
                    DB::raw('(SELECT COUNT(id) FROM order_items WHERE order_id = oi.order_id) AS order_counter'),
                    DB::raw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "cancelled" AND order_id = oi.order_id) AS order_cancel_counter'),
                    DB::raw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "returned" AND order_id = oi.order_id) AS order_return_counter')
                )
                ->leftJoin('product_variants AS pv', 'pv.id', '=', 'oi.product_variant_id')
                ->leftJoin('products AS p', 'pv.product_id', '=', 'p.id')
                ->leftJoin('product_ratings AS pr', function ($join) use ($prCondition) {
                    $join->on('pv.product_id', '=', 'pr.product_id');
                    if (!empty($prCondition)) {
                        $join->whereRaw($prCondition);
                    }
                })
                // ->leftJoin('seller_store AS ss', 'ss.seller_id', '=', 'oi.seller_id')
                ->leftJoin('seller_store AS ss', function ($join) {
                    $join->on('ss.seller_id', '=', 'oi.seller_id')
                        ->on('ss.store_id', '=', 'oi.store_id');
                })
                ->leftJoin('users AS u', 'u.id', '=', 'ss.user_id')
                ->leftJoin('order_trackings AS ot', 'ot.order_item_id', '=', 'oi.id')
                ->leftJoin('users AS db', 'db.id', '=', 'oi.delivery_boy_id')
                ->leftJoin('users AS s', 's.id', '=', 'oi.seller_id')
                ->where('oi.order_type', 'regular_order')
                ->where('oi.order_id', $orderDetails[$i]->id)
                ->when(isset($seller_id) && $seller_id != null, function ($query) use ($seller_id) {
                    $query->where('oi.seller_id', $seller_id)
                        ->where("oi.active_status", "!=", 'awaiting');
                })
                ->when(isset($order_type) && $order_type != '', function ($query) use ($order_type) {
                    $query->where("p.type", $order_type == 'digital' ? '=' : '!=', 'digital_product');
                })
                ->when(isset($delivery_boy_id) && $delivery_boy_id != null, function ($query) use ($delivery_boy_id) {
                    $query->where('oi.delivery_boy_id', '=', $delivery_boy_id);
                })
                ->when(isset($status) && !empty($status) && is_array($status) && count($status) > 0, function ($query) use ($status) {
                    $query->whereIn('oi.active_status', array_map('trim', $status));
                })
                ->groupBy('oi.id')
                ->get();


            $comboOrderItemData = DB::table('order_items AS oi')
                ->select(
                    'oi.*',
                    'cp.id AS product_id',
                    'cp.is_cancelable',
                    'cp.is_attachment_required',
                    'cp.is_prices_inclusive_tax',
                    'cp.cancelable_till',
                    'cp.product_type',
                    'cp.slug',
                    'cp.download_allowed',
                    'cp.download_link',
                    'ss.store_name',
                    'u.longitude AS seller_longitude',
                    'u.mobile AS seller_mobile',
                    'u.address AS seller_address',
                    'u.latitude AS seller_latitude',
                    DB::raw('(SELECT username FROM users WHERE id = oi.delivery_boy_id) AS delivery_boy_name'),
                    'ss.store_description',
                    'ss.rating AS seller_rating',
                    'ss.logo AS seller_profile',
                    'ot.courier_agency',
                    'ot.tracking_id',
                    'ot.awb_code',
                    'ot.url',
                    DB::raw('(SELECT username FROM users WHERE id = ' . (!empty($orderDetails[$i]->main_seller_id) ? $orderDetails[$i]->main_seller_id : '0') . ') AS seller_name'),
                    'cp.is_returnable',
                    'cp.special_price',
                    'cp.price AS main_price',
                    'cp.image',
                    'cp.title AS product_name',
                    'cp.pickup_location',
                    'cp.weight',
                    'cp.rating AS product_rating',
                    'cr.rating AS user_rating',
                    'cr.title AS user_rating_title',
                    'cr.images AS user_rating_images',
                    'cr.comment AS user_rating_comment',
                    'oi.status AS status',
                    DB::raw('(SELECT COUNT(id) FROM order_items WHERE order_id = oi.order_id) AS order_counter'),
                    DB::raw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "cancelled" AND order_id = oi.order_id) AS order_cancel_counter'),
                    DB::raw('(SELECT COUNT(active_status) FROM order_items WHERE active_status = "returned" AND order_id = oi.order_id) AS order_return_counter')
                )
                ->leftJoin('combo_products AS cp', 'cp.id', '=', 'oi.product_variant_id')
                ->leftJoin('combo_product_ratings AS cr', function ($join) use ($crCondition) {
                    $join->on('cp.id', '=', 'cr.product_id');
                    if (!empty($crCondition)) {
                        $join->whereRaw($crCondition);
                    }
                })
                ->leftJoin('seller_store AS ss', function ($join) {
                    $join->on('ss.seller_id', '=', 'oi.seller_id')
                        ->on('ss.store_id', '=', 'oi.store_id');
                })
                ->leftJoin('order_trackings AS ot', 'ot.order_item_id', '=', 'oi.id')
                ->leftJoin('users AS u', 'u.id', '=', 'oi.user_id')
                ->orWhereIn('oi.order_id', [$orderDetails[$i]->id])
                ->where('oi.order_type', 'combo_order')
                ->when(isset($seller_id) && $seller_id != null, function ($query) use ($seller_id) {
                    $query->where('oi.seller_id', $seller_id);
                    $query->where("oi.active_status", "!=", 'awaiting');
                })
                ->when(isset($order_type) && $order_type != '' && $order_type == 'digital', function ($query) {
                    $query->where("cp.product_type", '=', 'digital_product');
                })
                ->when(isset($order_type) && $order_type != '' && $order_type == 'simple', function ($query) {
                    $query->where("cp.product_type", '!=', 'digital_product');
                })
                ->when(isset($delivery_boy_id) && $delivery_boy_id != null, function ($query) use ($delivery_boy_id) {
                    $query->where('oi.delivery_boy_id', '=', $delivery_boy_id);
                })
                ->when(isset($status) && !empty($status) && $status != '' && is_array($status) && count($status) > 0, function ($query) use ($status) {
                    $status = array_map('trim', $status);
                    $query->whereIn('oi.active_status', $status);
                })
                ->groupBy('oi.id')
                ->get();


            $orderItemData = $regularOrderItemData->merge($comboOrderItemData);
            // dd($orderItemData);
            //get return request data
            $return_request = fetchDetails(ReturnRequest::class, ['user_id' => $user_id]);

            if ($orderDetails[$i]->payment_method == "bank_transfer" || $orderDetails[$i]->payment_method == "direct_bank_transfer") {
                $bankTransfer = fetchDetails(OrderBankTransfers::class, ['order_id' => $orderDetails[$i]->id], ['attachments', 'id', 'status']);
                $bankTransfer = collect($bankTransfer); // convert array to collection because laravel map function is expecting a collection
                if (!$bankTransfer->isEmpty()) {

                    $bankTransfer = $bankTransfer->map(function ($attachment) {

                        return [
                            'id' => $attachment->id,
                            'attachment' => app(MediaService::class)->getMediaImageUrl($attachment->attachments),
                            // 'attachment' => asset($attachment->attachments),
                            'banktransfer_status' => $attachment->status,
                        ];
                    });
                }
            }

            $orderDetails[$i]->latitude = (isset($orderDetails[$i]->latitude) && !empty($orderDetails[$i]->latitude)) ? $orderDetails[$i]->latitude : "";
            $orderDetails[$i]->longitude = (isset($orderDetails[$i]->longitude) && !empty($orderDetails[$i]->longitude)) ? $orderDetails[$i]->longitude : "";
            $orderDetails[$i]->order_recipient_person = (isset($orderDetails[$i]->order_recipient_person) && !empty($orderDetails[$i]->order_recipient_person)) ? $orderDetails[$i]->order_recipient_person : "";
            $orderDetails[$i]->bank_transfer_attachments = (isset($bankTransfer) && !empty($bankTransfer)) ? $bankTransfer : [];
            $orderDetails[$i]->notes = (isset($orderDetails[$i]->notes) && !empty($orderDetails[$i]->notes)) ? $orderDetails[$i]->notes : "";
            $orderDetails[$i]->courier_agency = "";
            $orderDetails[$i]->tracking_id = "";
            $orderDetails[$i]->url = "";

            if (isset($orderDetails[$i]->address_id) && $orderDetails[$i]->address_id != "" && $orderDetails[$i]->address_id != null) {
                $city_id = fetchDetails(Address::class, ['id' => $orderDetails[$i]->address_id], 'city_id');
                $city_id = !$city_id->isEmpty() ? $city_id[0]->city_id : [];
            } else {
                $city_id = [];
            }

            $orderDetails[$i]->is_shiprocket_order = (isset($city_id) && $city_id == 0) ? 1 : 0;

            if (!empty($seller_id) && isset($orderDetails[$i]->seller_delivery_charge)) {
                $orderDetails[$i]->delivery_charge = $orderDetails[$i]->seller_delivery_charge;
            }

            if (isset($orderDetails[$i]->seller_promo_dicount)) {
                $orderDetails[$i]->promo_discount = $orderDetails[$i]->seller_promo_dicount;
            }

            $returnable_count = 0;
            $cancelable_count = 0;
            $already_returned_count = 0;
            $already_cancelled_count = 0;
            $return_request_submitted_count = 0;
            $total_tax_percent = $total_tax_amount = $item_subtotal = 0;
            $productVariantPrice = 0;
            for ($k = 0; $k < count($orderItemData); $k++) {
                // dd($orderItemData[$k]);
                if ($orderItemData[$k]->order_type == 'regular_order') {
                    // Get name from products table
                    $productVariant = Product_variants::find($orderItemData[$k]->product_variant_id);
                    // dd($productVariant);
                    if ($productVariant) {
                        $productVariantPrice = $productVariant->price;
                        $productVariantSpecialPrice = $productVariant->special_price;
                    }
                    $orderItemData[$k]->name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $orderItemData[$k]->product_id, $language_code);
                    $orderItemData[$k]->product_name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $orderItemData[$k]->product_id, $language_code);
                } else {
                    // Get name from combo_products table
                    $productVariant = ComboProduct::find($orderItemData[$k]->product_variant_id);
                    if ($productVariant) {
                        $productVariantPrice = $productVariant->price;
                        $productVariantSpecialPrice = $productVariant->special_price;
                    }
                    $orderItemData[$k]->name = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $orderItemData[$k]->product_id, $language_code);
                    $orderItemData[$k]->product_name = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $orderItemData[$k]->product_id, $language_code);
                }
                // dd($productVariantPrice);
                // dd($orderItemData[$k]->product_variant_id);
                $download_allowed[] = isset($orderItemData[$k]->download_allowed) ? intval($orderItemData[$k]->download_allowed) : 0;
                if (isset($orderItemData[$k]->quantity) && $orderItemData[$k]->quantity != 0) {
                    $price = $orderItemData[$k]->special_price != '' && $orderItemData[$k]->special_price != null && $orderItemData[$k]->special_price > 0 && $orderItemData[$k]->special_price < $orderItemData[$k]->main_price ? $orderItemData[$k]->special_price : $orderItemData[$k]->main_price;
                    $amount = $orderItemData[$k]->quantity * $price;
                }
                if (!empty($orderItemData)) {
                    $user_rating_images = json_decode($orderItemData[$k]->user_rating_images, true);
                    $orderItemData[$k]->user_rating_images = array();

                    if (!empty($user_rating_images)) {
                        $orderItemData[$k]->user_rating_images = array_map(function ($image) {
                            return app(MediaService::class)->getImageUrl($image, "", "", 'image');
                        }, $user_rating_images);
                    }

                    if (isset($orderItemData[$k]->is_prices_inclusive_tax) && $orderItemData[$k]->is_prices_inclusive_tax == 1) {
                        $price_tax_amount = $price - ($price * (100 / (100 + $orderItemData[$k]->tax_percent)));
                    } else {
                        $price_tax_amount = $price * ($orderItemData[$k]->tax_percent / 100);
                    }

                    $orderItemData[$k]->is_cancelable = intval($orderItemData[$k]->is_cancelable);
                    $orderItemData[$k]->is_attachment_required = intval($orderItemData[$k]->is_attachment_required);
                    $orderItemData[$k]->tax_amount = isset($price_tax_amount) && !empty($price_tax_amount) ? (float) number_format($price_tax_amount, 2) : 0.00;
                    $orderItemData[$k]->net_amount = $orderItemData[$k]->price - $orderItemData[$k]->tax_amount;
                    $item_subtotal += $orderItemData[$k]->sub_total;
                    $orderItemData[$k]->sub_total_of_price = $orderItemData[$k]->quantity * $productVariantPrice;
                    $orderItemData[$k]->seller_name = (!empty($orderItemData[$k]->seller_name)) ? $orderItemData[$k]->seller_name : '';
                    $orderItemData[$k]->awb_code = isset($orderItemData[$k]->awb_code) && !empty($orderItemData[$k]->awb_code) && $orderItemData[$k]->awb_code != 'NULL' ? $orderItemData[$k]->awb_code : '';
                    $orderItemData[$k]->store_description = (!empty($orderItemData[$k]->store_description)) ? $orderItemData[$k]->store_description : '';
                    $orderItemData[$k]->seller_rating = (!empty($orderItemData[$k]->seller_rating)) ? number_format($orderItemData[$k]->seller_rating, 1) : "0";
                    $orderItemData[$k]->seller_profile = (!empty($orderItemData[$k]->seller_profile)) ? app(MediaService::class)->getImageUrl($orderItemData[$k]->seller_profile, "", "", 'image') : '';
                    $orderItemData[$k]->seller_latitude = (isset($orderItemData[$k]->seller_latitude) && !empty($orderItemData[$k]->seller_latitude)) ? $orderItemData[$k]->seller_latitude : '';
                    $orderItemData[$k]->seller_longitude = (isset($orderItemData[$k]->seller_longitude) && !empty($orderItemData[$k]->seller_longitude)) ? $orderItemData[$k]->seller_longitude : '';
                    $orderItemData[$k]->seller_address = (isset($orderItemData[$k]->seller_address) && !empty($orderItemData[$k]->seller_address)) ? $orderItemData[$k]->seller_address : '';
                    $orderItemData[$k]->seller_mobile = (isset($orderItemData[$k]->seller_mobile) && !empty($orderItemData[$k]->seller_mobile)) ? $orderItemData[$k]->seller_mobile : '';
                    $orderItemData[$k]->attachment = (isset($orderItemData[$k]->attachment) && !empty($orderItemData[$k]->attachment)) ? asset('/storage/' . $orderItemData[$k]->attachment) : '';

                    if (isset($seller_id) && $seller_id != null) {
                        $orderItemData[$k]->otp = (app(SellerService::class)->getSellerPermission($orderItemData[$k]->seller_id, $store_id, "view_order_otp")) ? $orderItemData[$k]->otp : "0";
                    }
                    $orderItemData[$k]->pickup_location = isset($orderItemData[$k]->pickup_location) && !empty($orderItemData[$k]->pickup_location) && $orderItemData[$k]->pickup_location != 'NULL' ? $orderItemData[$k]->pickup_location : '';
                    $orderItemData[$k]->hash_link = isset($orderItemData[$k]->hash_link) && !empty($orderItemData[$k]->hash_link) && $orderItemData[$k]->hash_link != 'NULL' ? asset('storage' . $orderItemData[$k]->hash_link) : '';
                    $varaint_data = app(ProductService::class)->getVariantsValuesById($orderItemData[$k]->product_variant_id);

                    $orderItemData[$k]->varaint_ids = (!empty($varaint_data)) ? $varaint_data[0]['variant_ids'] : '';
                    $orderItemData[$k]->variant_values = (!empty($varaint_data)) ? $varaint_data[0]['variant_values'] : '';
                    $orderItemData[$k]->attr_name = (!empty($varaint_data)) ? $varaint_data[0]['attr_name'] : '';
                    $orderItemData[$k]->product_rating = (!empty($orderItemData[$k]->product_rating)) ? number_format($orderItemData[$k]->product_rating, 1) : "0";
                    $orderItemData[$k]->name = (!empty($orderItemData[$k]->name)) ? $orderItemData[$k]->name : $orderItemData[$k]->product_name;
                    $orderItemData[$k]->variant_values = (!empty($orderItemData[$k]->variant_values)) ? $orderItemData[$k]->variant_values : $orderItemData[$k]->variant_values;
                    $orderItemData[$k]->user_rating = (!empty($orderItemData[$k]->user_rating)) ? $orderItemData[$k]->user_rating : '0';
                    $orderItemData[$k]->user_rating_comment = (!empty($orderItemData[$k]->user_rating_comment)) ? $orderItemData[$k]->user_rating_comment : '';
                    $orderItemData[$k]->status = json_decode($orderItemData[$k]->status);

                    if (!in_array($orderItemData[$k]->active_status, ['returned', 'cancelled'])) {
                        $total_tax_percent = $total_tax_percent + $orderItemData[$k]->tax_percent;
                        $total_tax_amount = $orderItemData[$k]->tax_amount * $orderItemData[$k]->quantity;
                    }

                    $orderItemData[$k]->image_sm = (empty($orderItemData[$k]->image) || file_exists(public_path(config('constants.MEDIA_PATH') . $orderItemData[$k]->image)) == FALSE) ? str_replace('///', '/', app(MediaService::class)->getImageUrl('', '', '', 'image', 'NO_IMAGE')) : str_replace('///', '/', app(MediaService::class)->getImageUrl($orderItemData[$k]->image, 'thumb', 'sm'));
                    $orderItemData[$k]->image_md = (empty($orderItemData[$k]->image) || file_exists(public_path(config('constants.MEDIA_PATH') . $orderItemData[$k]->image)) == FALSE) ? str_replace('///', '/', app(MediaService::class)->getImageUrl('', '', '', 'image', 'NO_IMAGE')) : str_replace('///', '/', app(MediaService::class)->getImageUrl($orderItemData[$k]->image, 'thumb', 'md'));
                    $orderItemData[$k]->image = (empty($orderItemData[$k]->image) || file_exists(public_path(config('constants.MEDIA_PATH') . $orderItemData[$k]->image)) == FALSE) ? str_replace('///', '/', app(MediaService::class)->getImageUrl('', '', '', 'image', 'NO_IMAGE')) : str_replace('///', '/', app(MediaService::class)->getImageUrl($orderItemData[$k]->image));
                    $orderItemData[$k]->is_already_returned = ($orderItemData[$k]->active_status == 'returned') ? '1' : '0';
                    $orderItemData[$k]->is_already_cancelled = ($orderItemData[$k]->active_status == 'cancelled') ? '1' : '0';

                    $return_request_key = array_search($orderItemData[$k]->id, array_column($return_request->all(), 'order_item_id'));

                    if ($return_request_key !== false) {
                        $orderItemData[$k]->return_request_submitted = $return_request[$return_request_key]->status;

                        if ($orderItemData[$k]->return_request_submitted == '1') {
                            $return_request_submitted_count += $orderItemData[$k]->return_request_submitted;
                        }
                    } else {
                        $orderItemData[$k]->return_request_submitted = '';
                        $return_request_submitted_count = null;
                    }

                    $orderItemData[$k]->courier_agency = (isset($orderItemData[$k]->courier_agency) && !empty($orderItemData[$k]->courier_agency)) ? $orderItemData[$k]->courier_agency : "";
                    $orderItemData[$k]->tracking_id = (isset($orderItemData[$k]->tracking_id) && !empty($orderItemData[$k]->tracking_id)) ? $orderItemData[$k]->tracking_id : "";
                    $orderItemData[$k]->url = (isset($orderItemData[$k]->url) && !empty($orderItemData[$k]->url)) ? $orderItemData[$k]->url : "";
                    $orderItemData[$k]->shiprocket_order_tracking_url = (isset($orderItemData[$k]->awb_code) && !empty($orderItemData[$k]->awb_code) && $orderItemData[$k]->awb_code != '' && $orderItemData[$k]->awb_code != null) ? "https://shiprocket.co/tracking/" . $orderItemData[$k]->awb_code : "";
                    $orderItemData[$k]->deliver_by = (isset($orderItemData[$k]->delivery_boy_name) && !empty($orderItemData[$k]->delivery_boy_name)) ? $orderItemData[$k]->delivery_boy_name : "";
                    $orderItemData[$k]->delivery_boy_id = (isset($orderItemData[$k]->delivery_boy_id) && !empty($orderItemData[$k]->delivery_boy_id)) ? $orderItemData[$k]->delivery_boy_id : "";
                    $orderItemData[$k]->discounted_price = (isset($orderItemData[$k]->discounted_price) && !empty($orderItemData[$k]->discounted_price)) ? $orderItemData[$k]->discounted_price : "";
                    $orderItemData[$k]->delivery_boy_name = (isset($orderItemData[$k]->delivery_boy_name) && !empty($orderItemData[$k]->delivery_boy_name)) ? $orderItemData[$k]->delivery_boy_name : "";

                    if (($orderDetails[$i]->type == 'digital_product' && in_array(0, $download_allowed)) || ($orderDetails[$i]->type != 'digital_product' && in_array(0, $download_allowed))) {
                        $orderDetails[$i]->download_allowed = 0;
                        $orderItemData[$k]->download_link = '';
                        $orderItemData[$k]->download_allowed = 0;
                    } else {
                        $orderDetails[$i]->download_allowed = 1;
                        $orderItemData[$k]->download_link = asset('storage' . $orderItemData[$k]->download_link);
                        $orderItemData[$k]->download_allowed = 1;
                    }
                    $orderItemData[$k]->email = (isset($orderItemData[$k]->email) && !empty($orderItemData[$k]->email) ? $orderItemData[$k]->email : '');

                    $returnable_count += $orderItemData[$k]->is_returnable;
                    $cancelable_count += $orderItemData[$k]->is_cancelable;
                    $already_returned_count += $orderItemData[$k]->is_already_returned;
                    $already_cancelled_count += $orderItemData[$k]->is_already_cancelled;

                    $delivery_date = isset($orderItemData[$k]->status[3][1]) ? $orderItemData[$k]->status[3][1] : '';
                    $settings = app(SettingService::class)->getSettings('system_settings', true);
                    $settings = json_decode($settings, true);
                    $timestemp = strtotime($delivery_date);
                    $today = date('Y-m-d');
                    // Bug fix: database/migrations/2025_01_01_000016_baseline_default_settings.php's default
                    // system_settings row never seeded this key (it's only ever written once an admin saves
                    // the System Settings page), so this line 500'd with "Undefined array key
                    // max_days_to_return_item" on any order-detail fetch on a fresh install. Defaulting to 0
                    // days matches the codebase's own stated convention of not trusting settings keys to
                    // exist unconditionally (see that migration's own doc comment).
                    $return_till = date('Y-m-d', strtotime($delivery_date . ' + ' . ($settings['max_days_to_return_item'] ?? 0) . ' days'));
                }
            }

            if ($orderDetails[$i]->order_type == 'regular_order') {
                // Get name from products table
                $orderDetails[$i]->name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $orderDetails[$i]->product_id, $language_code);
            } else {
                // Get name from combo_products table
                $orderDetails[$i]->name = app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'name', $orderDetails[$i]->product_id, $language_code);
            }
            $orderDetails[$i]->delivery_time = (isset($orderDetails[$i]->delivery_time) && !empty($orderDetails[$i]->delivery_time)) ? $orderDetails[$i]->delivery_time : "";
            $orderDetails[$i]->delivery_date = (isset($orderDetails[$i]->delivery_date) && !empty($orderDetails[$i]->delivery_date)) ? $orderDetails[$i]->delivery_date : "";
            $orderDetails[$i]->is_returnable = ($returnable_count >= 1 && isset($delivery_date) && !empty($delivery_date) && $today < $return_till) ? 1 : 0;
            $orderDetails[$i]->is_cancelable = ($cancelable_count >= 1) ? 1 : 0;
            $orderDetails[$i]->is_already_returned = ($already_returned_count == count($orderItemData)) ? '1' : '0';
            $orderDetails[$i]->is_already_cancelled = ($already_cancelled_count == count($orderItemData)) ? '1' : '0';

            $orderDetails[$i]->user_profile_image = app(MediaService::class)->getMediaImageUrl($orderDetails[$i]->user_profile_image, 'USER_IMG_PATH');

            if ($return_request_submitted_count == null) {
                $orderDetails[$i]->return_request_submitted = '';
            } else {
                $orderDetails[$i]->return_request_submitted = ($return_request_submitted_count == count($orderItemData)) ? '1' : '0';
            }

            if ((isset($delivery_boy_id) && $delivery_boy_id != null) || (isset($seller_id) && $seller_id != null)) {

                $orderDetails[$i]->total = strval($item_subtotal);
                $orderDetails[$i]->final_total = strval($item_subtotal + $orderDetails[$i]->delivery_charge);

                $orderDetails[$i]->total_payable = strval($item_subtotal + $orderDetails[$i]->delivery_charge - $orderDetails[$i]->promo_discount - $orderDetails[$i]->wallet_balance);
            } else {
                $orderDetails[$i]->total = strval($orderDetails[$i]->total);
            }
            $orderDetails[$i]->item_total = $orderDetails[$i]->total + $orderDetails[$i]->discount;
            $orderDetails[$i]->address = (isset($orderDetails[$i]->address) && !empty($orderDetails[$i]->address)) ? outputEscaping($orderDetails[$i]->address) : "";
            $orderDetails[$i]->username = outputEscaping($orderDetails[$i]->username);
            $orderDetails[$i]->country_code = (isset($orderDetails[$i]->country_code) && !empty($orderDetails[$i]->country_code)) ? $orderDetails[$i]->country_code : '';
            $orderDetails[$i]->total_tax_percent = strval($total_tax_percent);
            $orderDetails[$i]->total_tax_amount = strval($total_tax_amount);
            unset($orderDetails[$i]->main_seller_id);
            if (isset($seller_id) && $seller_id != null) {
                if ($download_invoice == true || $download_invoice == 1) {
                }
            } else {
                if ($download_invoice == true || $download_invoice == 1) {
                }
            }

            if (!empty($orderItemData)) {

                $orderDetails[$i]->order_items = $orderItemData;
            } else {
                $orderDetails[$i]->order_items = [];
            }
        }
        // $collection = collect($orderDetails);
        $filteredOrders = collect($orderDetails)->filter(function ($order) {
            return $order->order_items->isNotEmpty(); // Keep only orders with items
        })->values();
        // dd($filteredOrders);
        $order_data['total'] = $total;
        $order_data['order_data'] = $filteredOrders;
        return $order_data;
    }

    public function validateOrderStatus($order_ids, $status, $table = 'order_items', $user_id = null, $fromuser = false, $parcel_type = '', $return_reason = null, $return_quantity = null)
    {
        $error = 0;
        $cancelable_till = '';
        $returnable_till = '';
        $is_already_returned = 0;
        $is_already_cancelled = 0;
        $is_returnable = 0;
        $is_cancelable = 0;
        $returnable_count = 0;
        $cancelable_count = 0;
        $return_request = 0;
        $check_status = ['received', 'processed', 'shipped', 'delivered', 'cancelled', 'returned'];
        $user = Auth::user();

        $roleIdsToCheck = [1, 3, 5];


        if (in_array(strtolower(trim($status)), $check_status)) {

            if ($table == 'order_items') {
                $activeStatus = OrderItems::whereIn('id', explode(',', $order_ids))->pluck('active_status')->toArray();

                if (in_array('cancelled', $activeStatus) || in_array('returned', $activeStatus)) {
                    $response = [
                        'error' => true,
                        'message' => "You can't update status once an item is cancelled or returned",
                        'data' => [],
                    ];

                    return $response;
                }
            }
            if ($table == 'parcels') {

                $parcelIds = explode(',', $order_ids);

                $results = DB::table('parcels as p')
                    ->leftJoin('parcel_items as pi', 'pi.parcel_id', '=', 'p.id')
                    ->whereIn('p.id', $parcelIds)
                    ->select('p.active_status', 'pi.order_item_id')
                    ->get();

                $orderItemIds = $results->pluck('order_item_id')->toArray();

                $activeStatuses = $results->pluck('active_status')->toArray();

                if (in_array("cancelled", $activeStatuses) || in_array("returned", $activeStatuses)) {
                    return [
                        'error' => true,
                        'message' => "You can't update status once item cancelled / returned",
                        'data' => []
                    ];
                }

                if (empty($orderItemIds)) {
                    return [
                        'error' => true,
                        'message' => "You can't update status. Something went wrong!",
                        'data' => []
                    ];
                }
            }

            $query = DB::table('order_items as oi')
                ->select('oi.id as order_item_id', 'oi.user_id', 'oi.product_variant_id', 'oi.order_id', 'oi.quantity');

            if ($parcel_type === 'combo_order') {
                $query->leftJoin('combo_products as cp', 'cp.id', '=', 'oi.product_variant_id')
                    ->addSelect('cp.*');
            } else {
                $query->leftJoin('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id')
                    ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
                    ->addSelect('p.*', 'pv.*');
            }
            $query->leftJoin('parcel_items as pi', 'pi.order_item_id', '=', 'oi.id')
                ->leftJoin('parcels as pr', 'pr.id', '=', 'pi.parcel_id')
                ->addSelect('pr.active_status', 'pr.status as parcel_status');


            if ($table === 'parcels') {
                $query->addSelect('pr.active_status', 'pr.status as order_item_status')
                    ->whereIn('oi.id', $orderItemIds)
                    ->groupBy('oi.id');
            } else {
                $query->addSelect('oi.active_status', 'oi.status as order_item_status');
                if ($table === 'orders') {
                    $query->where('oi.order_id', $order_ids);
                } else {
                    $query->whereIn('oi.id', explode(',', $order_ids));
                }
            }

            $productData = $query->get();

            $priority_status = [
                'received' => 0,
                'processed' => 1,
                'shipped' => 2,
                'delivered' => 3,
                'return_request_pending' => 4,
                'return_request_approved' => 5,
                'return_pickedup' => 8,
                'cancelled' => 6,
                'returned' => 7,
            ];

            $is_posted_status_set = $canceling_delivered_item = $returning_non_delivered_item = false;
            $is_posted_status_set_count = 0;

            for ($i = 0; $i < count($productData); $i++) {
                /* check if there are any products returnable or cancellable products available in the list or not */
                if ($productData[$i]->is_returnable == 1) {
                    $returnable_count += 1;
                }
                if ($productData[$i]->is_cancelable == 1) {
                    $cancelable_count += 1;
                }

                /* check if the posted status is present in any of the variants */
                $productData[$i]->order_item_status = json_decode($productData[$i]->order_item_status, true);
                $order_item_status = array_column($productData[$i]->order_item_status, '0');
                if (in_array($status, $order_item_status)) {
                    $is_posted_status_set_count++;
                }


                /* if all are marked as same as posted status set the flag */
                if ($is_posted_status_set_count == count($productData)) {
                    $is_posted_status_set = true;
                }

                /* check if user is cancelling the order after it is delivered */
                if (($status == "cancelled") && (in_array("delivered", $order_item_status) || in_array("returned", $order_item_status))) {
                    $canceling_delivered_item = true;
                }

                /* check if user is returning non delivered item */
                if (($status == "returned") && !in_array("delivered", $order_item_status)) {
                    $returning_non_delivered_item = true;
                }
            }
            if ($table == 'parcels' && $status == 'returned') {
                $response['error'] = true;
                $response['message'] = "You cannot return Parcel Order!";
                $response['data'] = array();
                return $response;
            }
            if ($is_posted_status_set == true) {
                $response['error'] = true;
                $response['message'] = "Order is already marked as $status. You cannot set it again!";
                $response['data'] = array();
                return $response;
            }

            if ($canceling_delivered_item == true) {
                /* when user is trying cancel delivered order / item */
                $response['error'] = true;
                $response['message'] = "You cannot cancel delivered or returned order / item. You can only return that!";
                $response['data'] = array();
                return $response;
            }
            if ($returning_non_delivered_item == true) {
                /* when user is trying return non delivered order / item */
                $response['error'] = true;
                $response['message'] = "You cannot return a non-delivered order / item. First it has to be marked as delivered and then you can return it!";
                $response['data'] = array();
                return $response;
            }

            $is_returnable = ($returnable_count >= 1) ? 1 : 0;
            $is_cancelable = ($cancelable_count >= 1) ? 1 : 0;

            for ($i = 0; $i < count($productData); $i++) {

                if ($productData[$i]->active_status == 'returned') {
                    $error = 1;
                    $is_already_returned = 1;
                    break;
                }

                if ($productData[$i]->active_status == 'cancelled') {
                    $error = 1;
                    $is_already_cancelled = 1;
                    break;
                }

                if ($status == 'returned' && $productData[$i]->is_returnable == 0) {
                    $error = 1;
                    break;
                }

                if ($status == 'returned' && $productData[$i]->is_returnable == 1 && $priority_status[$productData[$i]->active_status] < 3) {
                    $error = 1;
                    $returnable_till = 'delivery';
                    break;
                }

                if ($status == 'cancelled' && $productData[$i]->is_cancelable == 1) {
                    $max = $priority_status[$productData[$i]->cancelable_till];
                    $min = $priority_status[$productData[$i]->active_status];

                    if ($min > $max) {
                        $error = 1;
                        $cancelable_till = $productData[$i]->cancelable_till;
                        break;
                    }
                }

                if ($status == 'cancelled' && $productData[$i]->is_cancelable == 0) {
                    $error = 1;
                    break;
                }
            }

            if ($status == 'returned' && $error == 1 && !empty($returnable_till)) {
                return response()->json([
                    'error' => true,
                    'message' => (count($productData) > 1) ? "One of the order item is not delivered yet!" : "The order item is not delivered yet!",
                    'data' => [],
                ]);
            }

            if ($status == 'returned' && $error == 1 && !$user && !$user->roles->whereIn('role_id', $roleIdsToCheck)) {
                return response()->json([
                    'error' => true,
                    'message' => (count($productData) > 1) ? "One of the order item can't be returned!" : "The order item can't be returned!",
                    'data' => $productData,
                ]);
            }

            if ($status == 'cancelled' && $error == 1 && !empty($cancelable_till) && !$user && !$user->roles->whereIn('role_id', $roleIdsToCheck)) {
                return response()->json([
                    'error' => true,
                    'message' => (count($productData) > 1) ? "One of the order item can be cancelled till " . $cancelable_till . " only" : "The order item can be cancelled till " . $cancelable_till . " only",
                    'data' => [],
                ]);
            }

            if ($status == 'cancelled' && $error == 1 && !$user && !$user->roles->whereIn('role_id', $roleIdsToCheck)) {
                return response()->json([
                    'error' => true,
                    'message' => (count($productData) > 1) ? "One of the order item can't be cancelled!" : "The order item can't be cancelled!",
                    'data' => [],
                ]);
            }

            for ($i = 0; $i < count($productData); $i++) {


                if ($status == 'returned' && $productData[$i]->is_returnable == 1 && $error == 0) {
                    $error = 1;
                    $return_request_flag = 1;

                    $return_status = [
                        'is_already_returned' => $is_already_returned,
                        'is_already_cancelled' => $is_already_cancelled,
                        'return_request_submitted' => $return_request,
                        'is_returnable' => $is_returnable,
                        'is_cancelable' => $is_cancelable,
                    ];

                    if ($fromuser == true || $fromuser == 1) {

                        // Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): a customer can now request a return for
                        // part of an item's ordered quantity rather than always the whole line item - reject
                        // a request for more than what was actually ordered.
                        if ($return_quantity !== null && (!is_numeric($return_quantity) || $return_quantity <= 0 || $return_quantity > (int) $productData[$i]->quantity)) {
                            $response['error'] = true;
                            $response['message'] = 'Return quantity must be between 1 and the ordered quantity (' . (int) $productData[$i]->quantity . ').';
                            $response['data'] = array();
                            return $response;
                        }

                        if ($table == 'order_items') {

                            if (isExist(['user_id' => $productData[$i]->user_id, 'order_item_id' => $productData[$i]->order_item_id, 'order_id' => $productData[$i]->order_id], ReturnRequest::class)) {

                                $response['error'] = true;
                                $response['message'] = "Return request already submitted !";
                                $response['data'] = array();
                                $response['return_status'] = $return_status;
                                return $response;
                            }
                            $request_data_item_data = $productData[$i];
                            $this->setUserReturnRequest($request_data_item_data, $table, $return_reason, $return_quantity);
                        } else {
                            for ($j = 0; $j < count($productData); $j++) {
                                if (isExist(['user_id' => $productData[$i]->user_id, 'order_item_id' => $productData[$i]->order_item_id, 'order_id' => $productData[$i]->order_id], ReturnRequest::class)) {

                                    $response['error'] = true;
                                    $response['message'] = "Return request already submitted !";
                                    $response['data'] = array();
                                    $response['return_status'] = $return_status;
                                    return $response;
                                }
                            }
                            $request_data_overall_item_data = $productData[$i];
                            $this->setUserReturnRequest($request_data_overall_item_data, $table, $return_reason, $return_quantity);
                        }
                    }

                    $response['error'] = false;
                    $response['message'] = "Return request submitted successfully !";
                    $response['return_request_flag'] = 1;
                    $response['data'] = array();
                    return $response;
                }
            }
            $response['error'] = false;
            $response['message'] = " ";
            $response['data'] = array();

            return $response;
        } else {
            $response['error'] = true;
            $response['message'] = "Invalid Status Passed";
            $response['data'] = array();
            return $response;
        }
    }

    public function update_order_item($id, $status, $return_request = 0, $fromapp = false, $return_reason = null, $return_quantity = null)
    {
        if ($return_request == 0) {
            $res = $this->validateOrderStatus($id, $status, 'order_items', '', true, '', $return_reason, $return_quantity);

            if ($res['error']) {
                $response['error'] = (isset($res['return_request_flag'])) ? false : true;
                $response['message'] = $res['message'];
                $response['data'] = $res['data'];
                return $response;
            }
        }
        if ($fromapp == true) {
            if ($status == 'returned') {
                $status = 'return_request_pending';
            }
        }
        $order_item_details = fetchDetails(OrderItems::class, ['id' => $id], ['order_id', 'seller_id']);
        $order_details = $this->fetchOrders($order_item_details[0]->order_id);
        $order_tracking_data = app(ShiprocketService::class)->getShipmentId($id, $order_item_details[0]->order_id);
        if (!$order_item_details->isEmpty()) {
            $order_details = $order_details['order_data'];
            $order_items_details = $order_details[0]->order_items;
            $key = array_search($id, array_column($order_items_details->toArray(), 'id'));
            $order_id = $order_details[0]->id;
            $store_id = $order_details[0]->store_id;
            $user_id = $order_details[0]->user_id;
            $order_counter = $order_items_details[$key]->order_counter;
            $order_cancel_counter = $order_items_details[$key]->order_cancel_counter;
            $order_return_counter = $order_items_details[$key]->order_return_counter;
            $seller_id = Seller::where('id', $order_item_details[0]->seller_id)->value('user_id');
            $user_res = fetchDetails(User::class, ['id' => $seller_id], ['fcm_id', 'username']);



            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $seller_id)
                ->get()
                ->map(function ($fcm) {
                    return [
                        'fcm_id' => $fcm->fcm_id,
                        'is_notification_on' => $fcm->user?->is_notification_on,
                    ];
                });
            // dd($results);
            $fcm_ids = [];
            foreach ($results as $result) {
                $fcm_ids[] = $result['fcm_id'];
            }
            // dd($fcm_ids);
            $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
            if ($order_items_details[$key]->active_status == 'cancelled') {
                $response['error'] = true;
                $response['message'] = 'Status Already Updated';
                $response['data'] = array();
                return $response;
            }
            if ($this->updateOrder(['status' => $status], ['id' => $id], true, 'order_items', '', '', OrderItems::class)) {
                $this->updateOrder(['active_status' => $status], ['id' => $id], false, 'order_items', '', '', OrderItems::class);

                //send notification while order cancelled
                if ($status == 'cancelled') {
                    $fcm_admin_subject = 'Order cancelled';
                    $fcm_admin_msg = 'Hello ' . $user_res[0]->username . 'order of order item id ' . $id . ' is cancelled.';
                    if (!empty($fcm_ids)) {
                        $fcmMsg = array(
                            'title' => "$fcm_admin_subject",
                            'body' => "$fcm_admin_msg",
                            'type' => "place_order",
                            'store_id' => "$store_id",
                            'content_available' => true
                        );
                        app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg, );
                    }
                    if (isset($order_tracking_data) && !empty($order_tracking_data) && $order_tracking_data != null) {
                        app(ShiprocketService::class)->cancelShiprocketOrder($order_tracking_data[0]['shiprocket_order_id']);
                    }
                }
            }

            $response['error'] = false;
            $response['message'] = 'Status Updated Successfully';
            $response['data'] = array();
            return $response;
        }
    }

    public function updateOrder($set, $where, $isJson = false, $table = 'order_items', $fromUser = false, $is_digital_product = 0, $modal = '')
    {

        if ($isJson == true) {
            $field = array_keys($set);
            $currentStatus = $set[$field[0]];

            $res = fetchDetails($modal, $where, '*');
            if ($is_digital_product == 1) {
                $priorityStatus = [
                    'received' => 0,
                    'delivered' => 1,
                ];
            } else {
                if ($set['status'] != 'return_request_decline') {
                    $priorityStatus = [
                        'received' => 0,
                        'processed' => 1,
                        'shipped' => 2,
                        'delivered' => 3,
                        'return_request_pending' => 4,
                        'return_request_approved' => 5,
                        'return_pickedup' => 8,
                        'cancelled' => 6,
                        'returned' => 7,
                    ];
                } else {
                    $priorityStatus = [
                        'received' => 0,
                        'processed' => 1,
                        'shipped' => 2,
                        'delivered' => 3,
                        'return_request_pending' => 4,
                        'return_request_decline' => 5,
                        'return_pickedup' => 8,
                        'cancelled' => 6,
                        'returned' => 7,
                    ];
                }
            }
            if (count($res) >= 1) {
                $i = 0;
                foreach ($res as $row) {
                    $set = [];
                    $temp = [];
                    $activeStatus = [];
                    $activeStatus[$i] = json_decode($row->status, true);
                    $currentSelectedStatus = end($activeStatus[$i]);
                    $temp = $activeStatus[$i];
                    $cnt = count($temp);
                    $originalDateTime = now();
                    $carbonDateTime = Carbon::parse($originalDateTime);
                    $currTime = $carbonDateTime->format('d-m-Y h:i:sa');
                    $minValue = (!empty($temp) && $temp[0][0] != 'awaiting') ? $priorityStatus[$currentSelectedStatus[0]] : -1;
                    $maxValue = $priorityStatus[$currentStatus];
                    if ($currentStatus == 'returned' || $currentStatus == 'cancelled') {
                        $temp[$cnt] = [$currentStatus, $currTime];
                    } else {
                        foreach ($priorityStatus as $key => $value) {
                            if ($value > $minValue && $value <= $maxValue) {
                                $temp[$cnt] = [$key, $currTime];
                            }
                            ++$cnt;
                        }
                    }
                    $set = [$field[0] => json_encode(array_values($temp))];
                    DB::beginTransaction();
                    try {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update($set);

                        DB::commit();
                        $response = true;
                    } catch (\Exception $e) {
                        DB::rollback();
                        $response = false;
                    }

                    // Additional code for commission and transactions can be added here
                    if ($currentStatus == 'delivered') {
                        if ($table == "parcels") {
                            $parcel_items = fetchDetails(ParcelItem::class, ['parcel_id' => $where['id']]);
                            $order_item_ids = array_map(function ($item) {
                                return $item['order_item_id']; // now $item is an array, so use array syntax
                            }, $parcel_items->toArray());
                            $order_item_ids = $order_item_ids ?? [];
                            $order = fetchDetails(OrderItems::class, '', ['delivery_boy_id', 'order_id', 'sub_total', 'id', 'seller_id'], '', '', '', '', 'id', $order_item_ids);
                        } else {
                            $order = OrderItems::where($where)->first(['delivery_boy_id', 'order_id', 'sub_total']);
                        }
                        $order_id = $row->order_id;
                        $total_order_items = DB::table('order_items as oi')->where('order_id', $order_id)
                            ->selectRaw('COUNT(oi.id) as total')->get()->toarray();
                        $total_order_items = $total_order_items[0]->total > 0 ? $total_order_items[0]->total : 1;
                        $order_final_total = fetchDetails(Order::class, ['id' => $order_id], ['delivery_charge', 'total', 'final_total', 'payment_method', 'promo_discount', 'is_cod_collected', 'wallet_balance']);
                        $delivery_charges = !$order_final_total->isEmpty() ? intval($order_final_total[0]->delivery_charge) : 0;
                        $order_item_delivery_charges = $delivery_charges / $total_order_items * $total_order_items;
                        if ($table == "parcels") {
                            if (!empty($order)) {
                                $deliveryBoyId = isset($order['delivery_boy_id']) ? $order['delivery_boy_id'] : $order[0]->delivery_boy_id;
                                $subtotal_of_products = $order_final_total[0]->total;
                                $total = 0;
                                if ($deliveryBoyId > 0) {
                                    $commission = 0;
                                    $deliveryBoy = User::where('id', $deliveryBoyId)->first(['bonus', 'bonus_type']);
                                    if (!empty($deliveryBoy)) {
                                        foreach ($order as $value) {
                                            $finalTotal = $total += $value->sub_total;
                                        }
                                        $settings = app(SettingService::class)->getSettings('system_settings', true);
                                        $settings = json_decode($settings, true);
                                        // dd($settings);
                                        // Get bonus_type
                                        if ($deliveryBoy->bonus_type == "fixed_amount_per_order_item") {
                                            $commission = (isset($deliveryBoy->bonus) && $deliveryBoy->bonus > 0) ? $deliveryBoy->bonus : $settings['delivery_boy_bonus'];
                                        }

                                        if ($deliveryBoy->bonus_type == "percentage_per_order_item") {
                                            $commission = (isset($deliveryBoy->bonus) && $deliveryBoy->bonus > 0) ? $deliveryBoy->bonus : $settings['delivery_boy_bonus'];
                                            $commission = $finalTotal * ($commission / 100);

                                            if ($commission > $finalTotal) {
                                                $commission = $finalTotal;
                                            }
                                        }
                                    }
                                    if ($total > 0 && $subtotal_of_products > 0) {
                                        $total_discount_percentage = app(OrderService::class)->calculatePercentage($total, $subtotal_of_products);
                                    }
                                    $wallet_balance = $order_final_total[0]->wallet_balance ?? 0;
                                    $promo_discount = $order_final_total[0]->promo_discount ?? 0;

                                    if ($promo_discount != 0) {
                                        $promo_discount = calculatePrice($total_discount_percentage, $promo_discount);
                                    }
                                    if ($wallet_balance != 0) {
                                        $wallet_balance = calculatePrice($total_discount_percentage, $wallet_balance);
                                    }
                                    $total_amount_payable = intval($finalTotal + $order_item_delivery_charges - $wallet_balance - $promo_discount);
                                    // Commission must be greater than zero to be credited into the account
                                    if ($commission > 0) {
                                        $transactionData = [
                                            'transaction_type' => "wallet",
                                            'user_id' => $deliveryBoyId,
                                            'order_id' => $order[0]->order_id,
                                            'type' => "credit",
                                            'txn_id' => "",
                                            'amount' => $commission,
                                            'status' => "success",
                                            'message' => "Order delivery bonus for order item ID: #" . $order[0]->id,
                                        ];
                                        Transaction::create($transactionData);
                                        app(WalletService::class)->updateBalance($commission, $deliveryBoyId, 'add');
                                    }
                                    if (strtolower($order_final_total[0]->payment_method) == "cod") {
                                        $transactionData = [
                                            'transaction_type' => "transaction",
                                            'user_id' => $deliveryBoyId,
                                            'order_id' => $row->order_id,
                                            'type' => "delivery_boy_cash",
                                            'txn_id' => "",
                                            'amount' => $total_amount_payable,
                                            'status' => "1",
                                            'message' => "Delivery boy collected COD",
                                        ];

                                        Transaction::create($transactionData);
                                        app(WalletService::class)->updateCashReceived($finalTotal, $deliveryBoyId, "add");
                                    }
                                }
                            }
                        }
                    }

                    ++$i;
                }
                return $response;
            }
        } else {
            DB::beginTransaction();
            try {
                DB::table($table)
                    ->where($where)
                    ->update($set);

                DB::commit();
                $response = true;
            } catch (\Exception $e) {
                DB::rollback();
                $response = false;
            }
            return $response;
        }
    }

    public function getOrderDetails($where = null, $status = false, $sellerId = null, $store_id = '')
    {
        // get data of regular order items
        $regularOrderItemData = DB::table('order_items as oi')
            ->select(
                'oi.*',
                'ot.courier_agency',
                'ot.tracking_id',
                'ot.url',
                'oi.otp as item_otp',
                'a.name as user_name',
                'oi.id as order_item_id',
                'oi.seller_id as oi_seller_id',
                'p.*',
                'v.product_id',
                'o.*',
                'o.email as user_email',
                'o.id as order_id',
                'o.total as order_total',
                'o.wallet_balance',
                'oi.active_status as oi_active_status',
                'u.email',
                'u.username as uname',
                'oi.status as order_status',
                'oi.attachment',
                'p.id as product_id',
                'p.pickup_location as pickup_location',
                'p.slug as product_slug',
                'p.sku as product_sku',
                'v.sku',
                't.txn_id',
                'oi.price',
                'p.name as pname',
                'p.type',
                'p.image as product_image',
                'p.is_prices_inclusive_tax',
                'p.is_attachment_required',
                'u.image as user_profile',
                'ss.store_name',
                'ss.logo as shop_logo',
                'v.price as product_price',
                'v.special_price as product_special_price',
                DB::raw('(SELECT username FROM users db where db.id=oi.delivery_boy_id ) as delivery_boy'),
                DB::raw('(SELECT mobile FROM addresses a where a.id=o.address_id ) as mobile_number')
            )
            ->leftJoin('product_variants as v', 'oi.product_variant_id', '=', 'v.id')
            ->leftJoin('transactions as t', 'oi.order_id', '=', 't.order_id')
            ->leftJoin('products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'oi.user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('order_trackings as ot', 'ot.order_item_id', '=', 'oi.id')
            ->leftJoin('addresses as a', 'a.id', '=', 'o.address_id')
            ->leftJoin('seller_store as ss', 'ss.seller_id', '=', 'oi.seller_id')
            ->where('oi.order_type', 'regular_order');

        $regularOrderItemData->where('o.is_pos_order', 0);

        if (isset($where) && $where != null) {
            $regularOrderItemData->where($where);
            if ($status == true) {
                $regularOrderItemData->whereNotIn('oi.active_status', ['cancelled', 'returned']);
            }
        }

        if (!isset($where) && $status == true) {
            $regularOrderItemData->whereNotIn('oi.active_status', ['cancelled', 'returned']);
        }
        if (isset($store_id) && !empty($store_id)) {
            $regularOrderItemData->where('oi.store_id', $store_id);
        }

        $regularOrderItemData->groupBy('oi.id');
        $regularOrderItemData = $regularOrderItemData->get()->toArray();

        // dd($regularOrderItemData);
        // get data of combo order items

        $comboOrderItemData = DB::table('order_items as oi')
            ->select(
                'oi.*',
                'ot.courier_agency',
                'ot.tracking_id',
                'ot.url',
                'oi.otp as item_otp',
                'a.name as user_name',
                'oi.id as order_item_id',
                'cp.*',
                'cp.id as product_id',
                'o.*',
                'o.email as user_email',
                'o.id as order_id',
                'oi.seller_id as oi_seller_id',
                'o.total as order_total',
                'o.wallet_balance',
                'oi.active_status as oi_active_status',
                'u.email',
                'u.username as uname',
                'oi.status as order_status',
                'cp.id as product_id',
                'cp.pickup_location as pickup_location',
                'cp.slug as product_slug',
                'cp.sku as product_sku',
                'cp.sku',
                't.txn_id',
                'oi.price',
                'cp.title as pname',
                'cp.product_type as type',
                'cp.image as product_image',
                'cp.is_prices_inclusive_tax',
                'u.image as user_profile',
                'ss.store_name',
                'ss.logo as shop_logo',
                'cp.price as product_price',
                'cp.special_price as product_special_price',
                DB::raw('(SELECT username FROM users db where db.id=oi.delivery_boy_id ) as delivery_boy'),
                DB::raw('(SELECT mobile FROM addresses a where a.id=o.address_id ) as mobile_number')
            )

            ->leftJoin('combo_products AS cp', 'cp.id', '=', 'oi.product_variant_id')
            ->leftJoin('transactions as t', 'oi.order_id', '=', 't.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'oi.user_id')
            ->leftJoin('orders as o', 'o.id', '=', 'oi.order_id')
            ->leftJoin('order_trackings as ot', 'ot.order_item_id', '=', 'oi.id')
            ->leftJoin('addresses as a', 'a.id', '=', 'o.address_id')
            ->leftJoin('seller_store as ss', 'ss.seller_id', '=', 'oi.seller_id')
            ->where('oi.order_type', 'combo_order');


        $comboOrderItemData->where('o.is_pos_order', 0);

        if (isset($where) && $where != null) {
            $comboOrderItemData->where($where);
            if ($status == true) {
                $comboOrderItemData->whereNotIn('oi.active_status', ['cancelled', 'returned']);
            }
        }

        if (!isset($where) && $status == true) {
            $comboOrderItemData->whereNotIn('oi.active_status', ['cancelled', 'returned']);
        }
        if (isset($store_id) && !empty($store_id)) {
            $comboOrderItemData->where('oi.store_id', $store_id);
        }
        $comboOrderItemData->groupBy('oi.id');
        $comboOrderItemData = $comboOrderItemData->get()->toArray();

        $orderResult = array_merge($regularOrderItemData, $comboOrderItemData);


        if (!empty($orderResult)) {
            foreach ($orderResult as $key => $value) {
                $orderResult[$key] = outputEscaping($value);
            }
        }

        return $orderResult;
    }

    public function ordersCount($status = "", $sellerId = "", $orderType = "", $store_id = "", $deliveryBoyId = "")
    {
        $query = OrderItems::query()
            ->whereHas('order', function ($q) {
                $q->where('is_pos_order', 0);
            })
            ->with(['productVariant.product']);

        // Filter by order type and status
        if (!empty($orderType)) {
            $query->whereHas('productVariant.product', function ($q) use ($orderType) {
                if ($orderType == 'digital') {
                    $q->where('type', 'digital_product');
                } elseif ($orderType == 'simple') {
                    $q->where('type', '!=', 'digital_product');
                }
            });

            if (!empty($status)) {
                $query->where('active_status', $status);
            }
        } elseif (!empty($status)) {
            $query->where('active_status', $status);
        }

        // Filter by seller
        if (!empty($sellerId)) {
            $query->where('seller_id', $sellerId)
                ->where('active_status', '!=', 'awaiting');
        }

        // Filter by delivery boy
        if (!empty($deliveryBoyId)) {
            $query->where('delivery_boy_id', $deliveryBoyId);
        }

        // Filter by store
        if (!empty($store_id)) {
            $query->where('store_id', $store_id)
                ->where('active_status', '!=', 'awaiting');
        }
        // dd($query->tosql(), $query->getbindings());
        // Count distinct order IDs
        $total = $query->distinct('order_id')->count('order_id');

        return $total;
    }

    public function fetchOrderItems($orderItemId = null, $userId = null, $status = null, $deliveryBoyId = null, $limit = 25, $offset = '0', $sort = null, $order = null, $startDate = null, $endDate = null, $search = null, $sellerId = null, $orderId = null, $store_id = "", $language_code = '')
    {

        // Type casting to ensure correct data types
        $limit = is_numeric($limit) ? (int) $limit : 25;
        $offset = is_numeric($offset) ? (int) $offset : 0;

        $res = $this->getOrderDetails(['o.id' => $orderId, 'oi.seller_id' => $sellerId], '', '', $store_id);
        // dd($orderId);
        if (empty($res)) {
            $order_type = fetchDetails(OrderItems::class, ['order_id' => $orderId], ['order_type']);
            $order_type = !$order_type->isEmpty() ? $order_type[0]->order_type : "";
        } else {
            $order_type = isset($res) && !empty($res) ? $res[0]->order_type : "";
        }


        $query = DB::table('order_items as oi')
            ->join('users as u', 'u.id', '=', 'oi.delivery_boy_id', 'left')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('users as un', 'un.id', '=', 'o.user_id', 'left')
            ->join('product_variants as pv', 'pv.id', '=', 'oi.product_variant_id', 'left');

        if ($order_type == 'combo_order') {
            $query->join('combo_products as cp', 'cp.id', '=', 'oi.product_variant_id', 'left')
                ->select('oi.*', 'o.address', 'un.username', 'o.wallet_balance', 'o.promo_discount', 'o.final_total', 'o.total', 'u.mobile', 'o.address_id', 'u.email', 'o.notes', 'o.created_at', 'o.delivery_date', 'o.delivery_time', 'o.delivery_charge','o.payment_method', 'o.is_cod_collected', 'o.total_payable', 'o.promo_discount', 'cp.id as product_id', 'cp.slug as product_slug', 'cp.sku', 'cp.title as pname', 'cp.is_returnable', 'cp.pickup_location', 'cp.is_cancelable', 'cp.is_attachment_required', 'cp.is_prices_inclusive_tax', 'cp.download_allowed', 'cp.image', 'cp.product_type as product_type', 'cp.id as combo_product_id', 'o.total as subtotal_of_order_items');
        } else {
            $query->join('products as p', 'p.id', '=', 'pv.product_id', 'left')
                ->select('oi.*', 'o.address', 'un.username', 'o.wallet_balance', 'o.promo_discount', 'o.total', 'o.final_total', 'u.mobile', 'u.email', 'o.address_id', 'o.notes', 'o.created_at', 'o.delivery_date', 'o.delivery_time','o.delivery_charge', 'o.is_cod_collected', 'o.total_payable', 'o.payment_method', 'o.promo_discount', 'p.slug as product_slug', 'p.id as product_id', 'p.sku', 'p.name as pname', 'p.pickup_location', 'p.is_returnable', 'p.is_cancelable', 'p.is_attachment_required', 'p.is_prices_inclusive_tax', 'p.hsn_code', 'p.download_allowed', 'p.image', 'p.type as product_type', 'o.total as subtotal_of_order_items');
        }
        if ($order_type === 'combo_order') {
            $query->join('seller_data as sd', 'sd.id', '=', 'cp.seller_id', 'left')
                ->join('seller_store as ss', 'ss.seller_id', '=', 'cp.seller_id', 'left');
        } else {
            $query->join('seller_data as sd', 'sd.id', '=', 'p.seller_id', 'left')
                ->join('seller_store as ss', 'ss.seller_id', '=', 'p.seller_id', 'left');
        }
        if (!empty($store_id)) {
            $query->where('oi.store_id', $store_id);
        }
        if (!empty($orderItemId)) {
            if (is_array($orderItemId)) {
                $query->whereIn('oi.id', $orderItemId);
            } else {
                $query->where('oi.id', $orderItemId);
            }
        }

        if (!empty($status)) {
            if (is_array($status)) {
                $query->whereIn('oi.active_status', $status);
            } else {
                $query->where('oi.active_status', $status);
            }
        }
        if (!empty($orderId)) {

            $query->where('oi.order_id', $orderId);
        }
        if (!empty($deliveryBoyId)) {
            $query->where('oi.delivery_boy_id', $deliveryBoyId);
        }
        if (!empty($sellerId)) {
            $query->where('oi.seller_id', $sellerId);
        }
        if (!empty($startDate) && !empty($endDate)) {
            $query->whereDate('oi.created_at', '>=', $startDate)
                ->whereDate('oi.created_at', '<=', $endDate);
        }
        if (!empty($startDate)) {
            $query->whereDate('o.created_at', '>=', $startDate);
        }
        if (!empty($endDate)) {
            $query->whereDate('o.created_at', '<=', $endDate);
        }
        if (!empty($search)) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->orWhere('u.username', 'like', '%' . $search . '%')
                    ->orWhere('u.email', 'like', '%' . $search . '%')
                    ->orWhere('oi.id', 'like', '%' . $search . '%');
            });
        }
        // Apply sorting
        if ($sort === 'created_at') {
            $sort = 'oi.created_at';
        }
        $query->orderBy($sort, $order);

        // Clone the query for the total count before applying pagination
        $totalQuery = clone $query;
        $totalCount = $totalQuery->count(DB::raw('DISTINCT oi.id')); // Count distinct order items to avoid duplicate rows due to joins

        // Apply pagination (limit and offset)
        if ($limit && $offset) {
            $query->skip($offset)->take($limit);
        }

        // Retrieve the order item data
        $order_item_data = $query->groupBy('oi.id')->get();
        // dd($order_item_data);
        // Initialize order details array
        $order_details = [];

        for ($k = 0; $k < count($order_item_data); $k++) {
            // dd($order_item_data[$k]->product_id);
            $multipleWhere = ['seller_id' => $order_item_data[$k]->seller_id, 'order_id' => $order_item_data[$k]->order_id];
            $order_charge_data = OrderCharges::where($multipleWhere)->get()->toArray();
            $return_request = fetchDetails(ReturnRequest::class, ['user_id' => $userId]);
            // dd($order_item_data[$k]->order_type);
            $productVariantPrice = 0;
            if ($order_item_data[$k]->order_type == 'regular_order') {
                $product_name = app(TranslationService::class)->getDynamicTranslation(
                    Product::class,
                    'name',
                    $order_item_data[$k]->product_id,
                    $language_code
                );
                $productVariant = Product_variants::find($order_item_data[$k]->product_variant_id);
                if ($productVariant) {
                    $productVariantPrice = $productVariant->price;
                }
            } elseif ($order_item_data[$k]->order_type == 'combo_order') {
                $product_name = app(TranslationService::class)->getDynamicTranslation(
                    ComboProduct::class,
                    'title',
                    $order_item_data[$k]->product_variant_id,
                    $language_code
                );
                $productVariant = ComboProduct::find($order_item_data[$k]->product_variant_id);
                if ($productVariant) {
                    $productVariantPrice = $productVariant->price;
                }
            }
            // dd($productVariantPrice);
            $order_item_data[$k]->pname = $product_name;
            // Decode status
            $order_item_data[$k]->status = json_decode($order_item_data[$k]->status);
            // Handle OTP logic
            if ($order_item_data[$k]->otp == 0) {
                if ($order_charge_data[0]['otp'] != 0) {
                    $order_item_data[$k]->otp = $order_charge_data[0]['otp'];
                } else {
                    $order_item_data[$k]->otp = '';
                }
            }

            // Format status dates
            if (!empty($order_item_data[$k]->status)) {
                foreach ($order_item_data[$k]->status as $index => $status) {
                    $order_item_data[$k]->status[$index][1] = date('d-m-Y h:i:sa', strtotime($status[1]));
                }
            }
            $order_item_data[$k]->image = app(MediaService::class)->getMediaImageUrl($order_item_data[$k]->image);
            // Set default values if not available
            $order_item_data[$k]->delivery_boy_id = $order_item_data[$k]->delivery_boy_id ?: '';
            $delivery_boy = User::where('id', $order_item_data[$k]->delivery_boy_id)->select('username')->get()->toarray();
            $delivery_boy = isset($delivery_boy) && !empty($delivery_boy[0]) ? $delivery_boy[0]['username'] : "";
            $order_item_data[$k]->discounted_price = $order_item_data[$k]->discounted_price ?: '';
            $order_item_data[$k]->deliver_by = $delivery_boy;
            $order_item_data[$k]->user_address = $order_item_data[$k]->address ?: '';
            unset($order_item_data[$k]->address);
// dd($productVariantPrice);
            // Variants data
            $variant_data = app(ProductService::class)->getVariantsValuesById($order_item_data[$k]->product_variant_id);
            // dd($variant_data);
            $order_item_data[$k]->variant_ids = $variant_data[0]['variant_ids'] ?? '';
            $order_item_data[$k]->variant_values = $variant_data[0]['variant_values'] ?? '';
            $order_item_data[$k]->attr_name = $variant_data[0]['attr_name'] ?? '';
            $order_item_data[$k]->sub_total_of_price = $order_item_data[$k]->quantity * $productVariantPrice;

            // Handle return/cancel logic
            $returnable_count = 0;
            $cancelable_count = 0;
            $already_returned_count = 0;
            $already_cancelled_count = 0;
            $return_request_submitted_count = 0;
            $total_tax_percent = 0;
            $total_tax_amount = 0;
            // Aggregate returnable and cancelable status
            if (!in_array($order_item_data[$k]->active_status, ['returned', 'cancelled'])) {
                $total_tax_percent += $order_item_data[$k]->tax_percent;
                $total_tax_amount += $order_item_data[$k]->tax_amount;
            }
            $order_item_data[$k]->is_already_returned = ($order_item_data[$k]->active_status == 'returned') ? '1' : '0';
            $order_item_data[$k]->is_already_cancelled = ($order_item_data[$k]->active_status == 'cancelled') ? '1' : '0';

            $return_request_array = $return_request->toArray();
            $return_request_key = array_search(
                $order_item_data[$k]->id,
                array_column($return_request_array, 'order_item_id')
            );
            if ($return_request_key !== false) {
                $order_item_data[$k]->return_request_submitted = $return_request[$return_request_key]->status;
                if ($order_item_data[$k]->return_request_submitted == '1') {
                    $return_request_submitted_count++;
                }
            } else {
                $order_item_data[$k]->return_request_submitted = '';
            }

            $returnable_count += $order_item_data[$k]->is_returnable;
            $cancelable_count += $order_item_data[$k]->is_cancelable;
            $already_returned_count += $order_item_data[$k]->is_already_returned;
            $already_cancelled_count += $order_item_data[$k]->is_already_cancelled;

            // Prepare final order details for each item
            $order_details[$k]['is_returnable'] = ($returnable_count >= 1) ? '1' : '0';
            $order_details[$k]['is_cancelable'] = ($cancelable_count >= 1) ? '1' : '0';
            $order_details[$k]['is_already_returned'] = ($already_returned_count == count($order_item_data)) ? '1' : '0';
            $order_details[$k]['is_already_cancelled'] = ($already_cancelled_count == count($order_item_data)) ? '1' : '0';
            $order_details[$k]['return_request_submitted'] = ($return_request_submitted_count == count($order_item_data)) ? '1' : '0';

            $order_details[$k]['username'] = outputEscaping($order_item_data[$k]->username);
            $order_details[$k]['total_tax_percent'] = strval($total_tax_percent);
            $order_details[$k]['total_tax_amount'] = strval($total_tax_amount);
        }
        // Prepare final response
        // dd($order_item_data);
        $order_data['total'] = $totalCount;
        $order_data['order_data'] = (!empty($order_item_data)) ? array_values($order_item_data->toArray()) : [];
        $order_data['order_details'] = (!empty($order_details)) ? $order_details : [];
        return $order_data;
    }

    public function getReturnOrderItemsList($deliveryBoyId = null, $search = "", $offset = 0, $limit = 10, $sort = "id", $order = 'ASC', $sellerId = null, $fromApp = '0', $orderItemId = '', $isPrint = '0', $orderStatus = '', $paymentMethod = '')
    {
        $query = OrderItems::with([
            'order.user',
            'order.address',
            'productVariant.product',
            'parcelItems.parcel',
            'parcelItems.parcel.order',
            'sellerData.user'
        ]);
        $language_code = app(TranslationService::class)->getLanguageCode();
        // Filters on order_items table
        if ($deliveryBoyId) {
            $query->where('delivery_boy_id', $deliveryBoyId)
                ->whereIn('active_status', ['return_pickedup', 'return_request_approved', 'returned']);
        }

        if ($orderStatus) {
            $query->where('active_status', $orderStatus);
        }

        if ($sellerId) {
            $query->where('seller_id', $sellerId)
                ->where('active_status', '!=', 'awaiting');
        }

        if ($orderItemId) {
            $query->where('id', $orderItemId);
        }

        if ($paymentMethod) {
            $query->whereHas('order', function ($q) use ($paymentMethod) {
                $q->where('payment_method', $paymentMethod);
            });
        }

        if (Request::has('user_id')) {
            $query->whereHas('order', function ($q) {
                $q->where('user_id', Request::get('user_id'));
            });
        }

        if (Request::has('start_date') && Request::has('end_date')) {
            $query->whereBetween('created_at', [Request::get('start_date'), Request::get('end_date')]);
        }

        $total = $query->count();

        $orderItems = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get();

        $rows = $orderItems->map(function ($orderItem, $key) use ($language_code) {
            $status = $orderItem->active_status;

            $badgeClass = match ($status) {
                'returned', 'cancelled' => 'bg-danger',
                'return_request_decline' => 'bg-danger',
                'return_request_approved' => 'bg-success',
                'return_request_pending' => 'bg-secondary',
                default => 'bg-secondary',
            };

            $statusText = match ($status) {
                'returned', 'cancelled' => 'Return Declined',
                'return_request_decline' => 'Return Declined',
                'return_request_approved' => 'Return Approved',
                'return_request_pending' => 'Return Requested',
                default => $status,
            };
            $product = optional($orderItem->productVariant)->product;
            $productName = $product ? app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code) : '';
            return [
                'id' => (string) ($key + 1),
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'user_id' => $orderItem->order->user_id,
                'username' => optional($orderItem->order->user)->username ?? '',
                'seller_name' => $orderItem->sellerData && $orderItem->sellerData->user
                    ? $orderItem->sellerData->user->username
                    : '',
                'sub_total' => $orderItem->sub_total,
                'product_name' => $productName,
                'product_image' => $orderItem->productVariant->product->image ?? '',
                'product_type' => $orderItem->productVariant->product->type ?? '',
                'payment_method' => $orderItem->order->payment_method,
                'variant_name' => $orderItem->productVariant->name ?? '',
                'quantity' => $orderItem->quantity,
                'discounted_price' => $orderItem->discounted_price,
                'price' => $orderItem->price,
                'active_status' => $status,
                'active_status_label' => "<label class='badge {$badgeClass}'>{$statusText}</label>",
                'created_at' => $orderItem->created_at->format('Y-m-d H:i:s'),
                'operate' => '<div class="d-flex align-items-center">
                <a href="' . url('delivery_boy/orders/' . $orderItem->order_id . '/returned_orders/' . $orderItem->id) . '" class="btn single_action_button" title="Edit">
                    <i class="bx bx-pencil mx-2"></i>
                </a>
            </div>',
            ];
        })->toArray();

        return $fromApp == '1' && $isPrint == '1' ? $rows : response()->json(['total' => $total, 'rows' => $rows]);
    }

    public function updateOrderItemStatus($order_item_id, $update_data)
    {
        $return_status = ['status' => '8'];
        OrderItems::where('id', $order_item_id)->update($update_data);

        ReturnRequest::where('order_item_id', $order_item_id)->update($return_status);
        return $update_data;
    }
    public function setUserReturnRequest($data, $table = 'orders', $reason = null, $quantity = null)
    {

        if ($table == 'orders') {
            foreach ($data as $row) {
                $requestData = [
                    'user_id' => $row['user_id'],
                    'product_id' => $row['product_id'],
                    'product_variant_id' => $row['product_variant_id'],
                    'order_id' => $row['order_id'],
                    'order_item_id' => $row['order_item_id'],
                    'reason' => $reason,
                    // Phase 3 (docs/PHASE_3_COMMERCE_CORE.md): a customer can now request a return for less
                    // than the full ordered quantity. Falls back to the order item's own quantity (still a
                    // whole-item return) when the caller doesn't provide one, matching the pre-existing
                    // behavior for older app clients that don't send this.
                    'quantity' => $quantity ?? ($row['quantity'] ?? null),
                ];
                ReturnRequest::create($requestData);
            }
        } else {
            $requestData = [
                'user_id' => $data->user_id,
                'product_id' => $data->product_id,
                'product_variant_id' => $data->product_variant_id,
                'order_id' => $data->order_id,
                'order_item_id' => $data->order_item_id,
                'reason' => $reason,
                'quantity' => $quantity ?? ($data->quantity ?? null),
            ];
            ReturnRequest::create($requestData);
        }
    }
    public function verifyPaymentTransaction($transaction_id = '', $payment_method = '', $additional_data = [])
    {
        $transaction_id = $transaction_id ?? '';
        $payment_method = trim($payment_method ?? '');
        $additional_data = is_array($additional_data) ? $additional_data : [];

        if (empty($payment_method)) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid payment method supplied',
                'code' => 102,
            ]);
        }

        switch ($payment_method) {
            case 'razorpay':
                $razorpay = new Razorpay();
                $payment = $razorpay->fetch_payments($transaction_id);

                if (!empty($payment) && isset($payment['status'])) {
                    $status = $payment['status'];
                    $amount = $payment['amount'] ?? 0;
                    $currency = $payment['currency'] ?? 'INR';

                    if ($status === 'authorized') {
                        $capture = $razorpay->capture_payment($amount, $transaction_id, $currency);
                        $capture_status = $capture['status'] ?? '';

                        $response = [
                            'error' => $capture_status !== 'captured',
                            'message' => match ($capture_status) {
                                'captured' => 'Payment captured successfully',
                                'refunded' => 'Payment is refunded.',
                                default => 'Payment could not be captured.',
                            },
                            'amount' => ($capture['amount'] ?? 0) / 100,
                            'data' => $capture,
                        ];
                    } else {
                        $response = [
                            'error' => $status !== 'captured',
                            'message' => match ($status) {
                                'captured' => 'Payment captured successfully',
                                'created' => 'Payment is just created and yet not authorized / captured!',
                                default => 'Payment is ' . ucwords($status) . '!',
                            },
                            'amount' => $amount / 100,
                            'data' => $payment,
                        ];
                    }
                } else {
                    $response['message'] = "Payment not found by the transaction ID!";
                }
                break;

            case 'paystack':
                $paystack = new Paystack();
                $result = $paystack->verify_transaction($transaction_id);

                if (!empty($result)) {
                    $payment = json_decode($result, true);
                    $status = $payment['data']['status'] ?? '';
                    $amount = $payment['data']['amount'] ?? 0;

                    $response = [
                        'error' => $status !== 'success',
                        'message' => $status === 'success'
                            ? 'Payment is successful'
                            : 'Payment is ' . ucwords($status) . '!',
                        'amount' => $amount / 100,
                        'data' => $payment,
                    ];
                } else {
                    $response['message'] = "Payment not found by the transaction ID!";
                }
                break;
        }

        return $response;
    }

    public function process_refund($id, $status, $type = 'order_items')
    {
        $possibleStatus = ["cancelled", "returned"];
        $system_settings = app(SettingService::class)->getSettings('system_settings', true);
        $system_settings = json_decode($system_settings, true);
        if (!in_array($status, $possibleStatus)) {
            $response = [
                'error' => true,
                'message' => 'Refund cannot be processed. Invalid status',
                'data' => [],
            ];

            return $response;
        }
        if ($type == 'order_items') {
            /* fetch order_id */
            $order_item_details = fetchDetails(OrderItems::class, ['id' => $id], ['order_id', 'id', 'seller_id', 'sub_total', 'quantity', 'status', 'store_id']);


            /* fetch order and its complete details with order_items */
            $order_id = !$order_item_details->isEmpty() ? $order_item_details[0]->order_id : "";
            $seller_id = !$order_item_details->isEmpty() ? $order_item_details[0]->seller_id : "";
            $store_id = !$order_item_details->isEmpty() ? $order_item_details[0]->store_id : "";

            $order_item_data = fetchDetails(OrderCharges::class, ['order_id' => $order_id, 'seller_id' => $seller_id], 'sub_total');
            $order_total = 0.00;
            if (!$order_item_data->isEmpty()) {
                $order_total = floatval($order_item_data[0]->sub_total);
            }
            $order_item_total = $order_item_details[0]->sub_total;

            $order_details = $this->fetchOrders($order_id);
            $order_details = $order_details['order_data'];

            $order_items_details = $order_details[0]->order_items;

            $key = array_search($id, array_column($order_items_details->toArray(), 'id'));

            $current_price = $order_items_details[$key]->sub_total;
            $order_item_id = $order_items_details[$key]->id;
            $currency = (isset($system_settings['currency']) && !empty($system_settings['currency'])) ? $system_settings['currency'] : '';
            $payment_method = $order_details[0]->payment_method;
            // dd($payment_method);
            //check for order active status
            $active_status = json_decode($order_item_details[0]->status, true);

            if (strtolower($payment_method) != 'wallet') {
                if ($active_status[1][0] == 'cancelled' && $active_status[0][0] == 'awaiting') {
                    $response['error'] = true;
                    $response['message'] = 'Refund cannot be processed.';
                    $response['data'] = array();
                    return $response;
                }
            }

            $total = $order_details[0]->total;
            $is_delivery_charge_returnable = isset($order_details[0]->is_delivery_charge_returnable) && $order_details[0]->is_delivery_charge_returnable == 1 ? '1' : '0';
            $delivery_charge = (isset($order_details[0]->delivery_charge) && !empty($order_details[0]->delivery_charge)) ? $order_details[0]->delivery_charge : 0;
            $promo_code = $order_details[0]->promo_code ?? "";
            $promo_discount = $order_details[0]->promo_discount;
            $final_total = $order_details[0]->final_total;
            $wallet_balance = $order_details[0]->wallet_balance;
            // dd($order_details[0]);
            $total_payable = $order_details[0]->total_payable;
            $user_id = $order_details[0]->user_id;

            $order_items_count = $order_details[0]->order_items[0]->order_counter;
            $cancelled_items_count = $order_details[0]->order_items[0]->order_cancel_counter;
            $returned_items_count = $order_details[0]->order_items[0]->order_return_counter;
            // dd($returned_items_count);
            $last_item = 0;


            $fcm_ids = array();

            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $user_id)
                ->whereHas('user', function ($q) {
                    $q->where('is_notification_on', 1);
                })
                ->get()
                ->map(function ($fcm) {
                    return [
                        'fcm_id' => $fcm->fcm_id,
                        'is_notification_on' => $fcm->user?->is_notification_on,
                    ];
                });
            $fcm_ids = [];
            foreach ($results as $result) {
                $fcm_ids[] = $result['fcm_id'];
            }
            // dd($order_items_count);
            if (($cancelled_items_count + $returned_items_count) == $order_items_count) {
                $last_item = 1;
            }
            $new_total = $total - $current_price;

            /* recalculate delivery charge */
            $new_delivery_charge = ($new_total > 0) ? app(DeliveryService::class)->recalulateDeliveryCharge($order_details[0]->address_id, $new_total, $delivery_charge, $store_id) : 0;

            /* recalculate promo discount */

            $new_promo_discount = app(PromoCodeService::class)->recalculatePromoDiscount($promo_code, $promo_discount, $user_id, $new_total, $payment_method, $new_delivery_charge, $wallet_balance);

            $new_final_total = $new_total + $new_delivery_charge - $new_promo_discount;
            $bank_receipt = fetchDetails(OrderBankTransfers::class, ['order_id' => $order_item_details[0]->order_id]);
            $bank_receipt_status = !$bank_receipt->isEmpty() ? $bank_receipt[0]->status : "";

            /* find returnable_amount, new_wallet_balance
            condition : 1
            */
            if (strtolower($payment_method) == 'cod' || $payment_method == 'bank_transfer' || $payment_method == 'direct_bank_transfer') {
                // dd($bank_receipt_status);
                /* when payment method is COD or Bank Transfer and payment is not yet done */
                if (strtolower($payment_method) == 'cod' || ($payment_method == 'bank_transfer' && (empty($bank_receipt_status) || $bank_receipt_status == "0" || $bank_receipt_status == "1"))) {
                    $returnable_amount = ($wallet_balance <= $current_price) ? $wallet_balance : (($wallet_balance > 0) ? $current_price : 0);
                    $returnable_amount = ($promo_discount != $new_promo_discount && $last_item == 0) ? $returnable_amount - $promo_discount + $new_promo_discount : $returnable_amount; /* if the new promo discount changed then adjust that here */
                    $returnable_amount = ($returnable_amount < 0) ? 0 : $returnable_amount;

                    /* if returnable_amount is 0 then don't change he wallet_balance */
                    $new_wallet_balance = ($returnable_amount > 0) ? (($wallet_balance <= $current_price) ? 0 : (($wallet_balance - $current_price > 0) ? $wallet_balance - $current_price : 0)) : $wallet_balance;
                    // dd($new_wallet_balance);
                }
            }
            /* if it is any other payment method or bank transfer with accepted receipts then payment is already done
            condition : 2
            */
            if ((strtolower($payment_method) != 'cod' && $payment_method != 'bank_transfer' || $payment_method !== 'direct_bank_transfer') || (($payment_method == 'bank_transfer' || $payment_method == 'direct_bank_transfer') && $bank_receipt_status == 2)) {
                //    dd('here');
                $returnable_amount = $current_price;
                $returnable_amount = ($promo_discount != $new_promo_discount) ? $returnable_amount - $promo_discount + $new_promo_discount : $returnable_amount;
                $returnable_amount = ($last_item == 1 && $is_delivery_charge_returnable == 1) ? $returnable_amount + $delivery_charge : $returnable_amount;  /* if its the last item getting cancelled then check if we have to return delivery charge or not */
                $returnable_amount = ($returnable_amount < 0) ? 0 : $returnable_amount;
                // dd($wallet_balance - $returnable_amount < 0);
                $new_wallet_balance = ($last_item == 1) ? 0 : (($wallet_balance - $returnable_amount < 0) ? 0 : $wallet_balance - $returnable_amount);
                // dd($new_wallet_balance);
            }

            /* find new_total_payable */
            if (strtolower($payment_method) != 'cod' && $payment_method != 'bank_transfer') {
                /* online payment or any other payment method is used. and payment is already done */
                $new_total_payable = 0;
            } else {
                if ($bank_receipt_status == 2) {
                    $new_total_payable = 0;
                } else {
                    $new_total_payable = $new_final_total - $new_wallet_balance;
                }
            }

            if ($new_total == 0) {
                $new_total = $new_wallet_balance = $new_delivery_charge = $new_final_total = $new_total_payable = 0;
            }

            //custom message
            $custom_notification = fetchDetails(CustomMessage::class, ['type' => "wallet_transaction"], '*');

            $hashtag_currency = '< currency >';
            $hashtag_returnable_amount = '< returnable_amount >';
            $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
            $hashtag = html_entity_decode($string);
            $data = str_replace(array($hashtag_currency, $hashtag_returnable_amount), array($currency, $returnable_amount), $hashtag);
            $message = outputEscaping(trim($data, '"'));
            $custom_message = !$custom_notification->isEmpty() ? $message : $currency . ' ' . $returnable_amount;
            $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Amount Credited To Wallet";
            // dd($returnable_amount);

            if ($returnable_amount > 0) {

                $fcmMsg = array(
                    'title' => "$title",
                    'body' => "$custom_message",
                    'type' => "wallet",
                    'store_id' => "$store_id",
                );
                $registrationIDs_chunks = array_chunk($fcm_ids, 1000);
                app(FirebaseNotificationService::class)->sendNotification('', $registrationIDs_chunks, $fcmMsg);

                if ($order_details[0]->payment_method == 'RazorPay') {
                    app(WalletService::class)->updateWalletBalance('refund', $user_id, $returnable_amount, 'Amount Refund for Order Item ID  : ' . $id, $order_item_id, '', 'razorpay');
                } else {
                    app(WalletService::class)->updateWalletBalance('credit', $user_id, $returnable_amount, 'Refund Amount Credited for Order Item ID  : ' . $id, $order_item_id);
                }
            }

            // recalculate delivery charge and promocode for each seller

            $order_delivery_charge = fetchDetails(OrderCharges::class, ['order_id' => $order_id, 'seller_id' => $seller_id], 'delivery_charge');
            $order_charges_data = DB::table('order_charges')
                ->where('order_id', $order_id)
                ->where('seller_id', '<>', $seller_id)
                ->get();

            if (!$order_delivery_charge->isEmpty()) {
                $parcel_total = floatval($order_total) - floatval($order_item_total);
                if ($parcel_total != 0) {
                    $seller_promocode_discount_percentage = ($parcel_total * 100) / $new_total;
                    $seller_promocode_discount = ($new_promo_discount * $seller_promocode_discount_percentage) / 100;
                    $seller_delivery_charge = ($new_delivery_charge * $seller_promocode_discount_percentage) / 100;
                    $parcel_final_total = $parcel_total + $seller_delivery_charge - $seller_promocode_discount;
                    $set = [
                        'promo_discount' => round($seller_promocode_discount, 2),
                        'delivery_charge' => round($seller_delivery_charge, 2),
                        'sub_total' => round($parcel_total, 2),
                        'total' => round($parcel_final_total, 2)
                    ];
                    updateDetails(
                        $set,
                        ['order_id' => $order_id, 'seller_id' => $seller_id],
                        OrderCharges::class
                    );
                }
            }
            if (isset($order_charges_data) && !empty($order_charges_data)) {
                foreach ($order_charges_data as $data) {

                    $total = $data->sub_total + $data->promo_discount - $data->delivery_charge;

                    $promocode_discount_percentage = ($data->sub_total * 100) / $new_total;
                    $promocode_discount = ($new_promo_discount * $promocode_discount_percentage) / 100;
                    $delivery_charge = ($new_delivery_charge * $promocode_discount_percentage) / 100;
                    $final_total = $data->sub_total + $delivery_charge - $promocode_discount;
                    $value = [
                        'promo_discount' => round($promocode_discount, 2),
                        'delivery_charge' => round($delivery_charge, 2),
                        'sub_total' => $data->sub_total,
                        'total' => round($final_total, 2)
                    ];

                    updateDetails(
                        $value,
                        ['order_id' => $order_id, 'seller_id' => $data->seller_id],
                        OrderCharges::class
                    );
                }
            }
            // end

            $set = [
                'total' => $new_total,
                'final_total' => $new_final_total,
                'total_payable' => $new_total_payable,
                'promo_discount' => (!empty($new_promo_discount) && $new_promo_discount > 0) ? $new_promo_discount : 0,
                // 'delivery_charge' => $new_delivery_charge,
                'wallet_balance' => $new_wallet_balance
            ];
            updateDetails($set, ['id' => $order_id], Order::class);
            $response['error'] = false;
            $response['message'] = 'Status Updated Successfully';
            $response['data'] = array();
            return $response;
        } elseif ($type == 'orders') {
            /* if complete order is getting cancelled */
            $order_details = $this->fetchOrders($id);

            $order_item_details = DB::table('order_items')
                ->select(DB::raw('SUM(tax_amount) as total_tax'), 'status')
                ->where('order_id', $order_details['order_data'][0]->id)->get();

            $order_details = $order_details['order_data'];
            $store_id = $order_details[0]->store_id;
            $payment_method = $order_details[0]->payment_method;

            $active_status = json_decode($order_item_details[0]->status, true);
            if (trim(strtolower($payment_method)) != 'wallet') {
                if (
                    (isset($active_status[1][0]) && $active_status[1][0] == 'cancelled') ||
                    (isset($active_status[0][0]) && $active_status[0][0] == 'awaiting')
                ) {
                    $response['error'] = true;
                    $response['message'] = 'Refund cannot be processed.';
                    $response['data'] = array();
                    return $response;
                }
            }

            $wallet_refund = true;
            $bank_receipt = fetchDetails(OrderBankTransfers::class, ['order_id' => $id]);

            $is_transfer_accepted = 0;

            if ($payment_method == 'bank_transfer') {
                if (!$bank_receipt->isEmpty()) {
                    foreach ($bank_receipt as $receipt) {
                        if ($receipt->status == 2) {
                            $is_transfer_accepted = 1;
                            break;
                        }
                    }
                }
            }
            if ($order_details[0]->wallet_balance == 0 && $status == 'cancelled' && $payment_method == 'bank_transfer' && (!$is_transfer_accepted || empty($bank_receipt))) {
                $wallet_refund = false;
            } else {
                $wallet_refund = true;
            }

            $promo_discount = $order_details[0]->promo_discount;
            $final_total = $order_details[0]->final_total;
            $is_delivery_charge_returnable = isset($order_details[0]->is_delivery_charge_returnable) && $order_details[0]->is_delivery_charge_returnable == 1 ? '1' : '0';
            $payment_method = strtolower($payment_method);
            $total_tax_amount = $order_item_details[0]->total_tax;
            $wallet_balance = $order_details[0]->wallet_balance;
            $currency = (isset($system_settings['currency']) && !empty($system_settings['currency'])) ? $system_settings['currency'] : '';
            $user_id = $order_details[0]->user_id;
            $fcmMsg = array(
                'title' => "Amount Credited To Wallet",
            );
            $user_res = fetchDetails(User::class, ['id' => $user_id], 'fcm_id');
            $results = UserFcm::with('user:id,id,is_notification_on')
                ->where('user_id', $user_id)
                ->whereHas('user', function ($q) {
                    $q->where('is_notification_on', 1);
                })
                ->get()
                ->map(function ($fcm) {
                    return [
                        'fcm_id' => $fcm->fcm_id,
                        'is_notification_on' => $fcm->user?->is_notification_on,
                    ];
                });
            $fcm_ids = [];
            foreach ($results as $result) {
                $fcm_ids[] = $result['fcm_id'];
            }

            if ($wallet_refund == true) {
                if ($payment_method != 'cod') {
                    /* update user's wallet */
                    if ($is_delivery_charge_returnable == 1) {
                        $returnable_amount = $order_details[0]->total + $order_details[0]->delivery_charge;
                    } else {
                        $returnable_amount = $order_details[0]->total;
                    }

                    if ($payment_method == 'bank_transfer' && !$is_transfer_accepted) {
                        $returnable_amount = $returnable_amount - $order_details[0]->total_payable;
                    }
                    //send custom notifications
                    $custom_notification = fetchDetails(CustomMessage::class, ['type' => "wallet_transaction"], '*');
                    $hashtag_currency = '< currency >';
                    $hashtag_returnable_amount = '< returnable_amount >';
                    $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                    $hashtag = html_entity_decode($string);
                    $data = str_replace(array($hashtag_currency, $hashtag_returnable_amount), array($currency, $returnable_amount), $hashtag);
                    $message = outputEscaping(trim($data, '"'));
                    $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Amount Credited To Wallet";
                    $body = !$custom_notification->isEmpty() ? $message : $currency . ' ' . $returnable_amount;
                    $fcmMsg = array(
                        'title' => "$title",
                        'body' => "$body",
                        'type' => "wallet",
                        'store_id' => "$store_id",
                    );
                    app(FirebaseNotificationService::class)->sendNotification('', $fcm_ids, $fcmMsg);

                    app(WalletService::class)->updateWalletBalance('credit', $user_id, $returnable_amount, 'Wallet Amount Credited for Order Item ID  : ' . $id);
                } else {
                    if ($wallet_balance != 0) {
                        /* update user's wallet */
                        $returnable_amount = $wallet_balance;
                        //send custom notifications
                        $custom_notification = fetchDetails(CustomMessage::class, ['type' => "wallet_transaction"], '*');
                        $hashtag_currency = '< currency >';
                        $hashtag_returnable_amount = '< returnable_amount >';
                        $string = !$custom_notification->isEmpty() ? json_encode($custom_notification[0]->message, JSON_UNESCAPED_UNICODE) : '';
                        $hashtag = html_entity_decode($string);
                        $data = str_replace(array($hashtag_currency, $hashtag_returnable_amount), array($currency, $returnable_amount), $hashtag);
                        $message = outputEscaping(trim($data, '"'));
                        $title = !$custom_notification->isEmpty() ? $custom_notification[0]->title : "Amount Credited To Wallet";
                        $body = !$custom_notification->isEmpty() ? $message : $currency . ' ' . $returnable_amount;
                        $fcmMsg = array(
                            'title' => "$title",
                            'body' => "$body",
                            'type' => "wallet",
                            'store_id' => "$store_id",
                        );
                        app(FirebaseNotificationService::class)->sendNotification('', $fcm_ids, $fcmMsg);


                        app(WalletService::class)->updateWalletBalance('credit', $user_id, $returnable_amount, 'Wallet Amount Credited for Order Item ID  : ' . $id);
                    }
                }
            }
        }
    }

    public function deliveryBoyOrdersCount($status = "", $deliveryBoyId = "", $table = '')
    {
        if ($table == 'parcels') {
            $query = Parcel::query()->selectRaw('count(DISTINCT `id`) as total');

            if (!empty($status)) {
                $query->where('active_status', $status);
            }
            if (!empty($deliveryBoyId)) {
                $query->where('delivery_boy_id', $deliveryBoyId);
            }

            $result = $query->get()->first();

            return $result->total;
        } else {
            $query = OrderItems::query()->selectRaw('count(DISTINCT `order_id`) as total');

            if (!empty($status)) {
                $query->where('active_status', $status);
            }

            if (!empty($deliveryBoyId)) {
                $query->where('delivery_boy_id', $deliveryBoyId);
            }

            $result = $query->get()->first();

            return $result->total;
        }
    }

    public function countNewOrders($type = '')
    {
        $user = Auth::user();
        $store_id = app(StoreService::class)->getStoreId();

        $query = Order::where('store_id', $store_id);

        // If user is a delivery boy (role_id = 3), join with order_items
        if (!empty($type) && $type !== 'api' && $user && $user->isDeliveryBoy()) {
            $query->whereHas('orderItems', function ($q) use ($user) {
                $q->where('delivery_boy_id', $user->id);
            });
        }

        if ($user && $user->isDeliveryBoy() && (empty($type) || $type === 'api')) {
            $query->whereHas('orderItems', function ($q) use ($user) {
                $q->where('delivery_boy_id', $user->id);
            });
        }

        $totalOrders = $query->count();

        return [
            'total_orders' => $totalOrders,
        ];
    }

    public function addBankTransferProof($data)
    {

        foreach ($data['attachments'] as $attachment) {

            OrderBankTransfers::create([
                'order_id' => $data['order_id'],
                'attachments' => $attachment['image_path'],
            ]);
        }

        return true;
    }
    function calculatePercentage($total, $price)
    {
        return ($price / $total) * 100;
    }
}