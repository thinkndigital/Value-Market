<?php

namespace App\Http\Controllers;

use App\Http\Controllers\TransactionController;
use App\Libraries\Midtrans;
use App\Libraries\Paystack;
use App\Libraries\Razorpay;
use App\Libraries\Stripe;
use App\Models\Address;
use App\Models\Cart;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Product_variants;
use App\Models\Promocode;
use App\Models\Product;
use App\Models\Store;
use App\Models\Tax;
use App\Models\User;
use App\Models\Zipcode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\CartService;
use App\Services\ProductService;
use App\Services\MediaService;
use App\Services\DeliveryService;
use App\Services\ShiprocketService;
use App\Services\CurrencyService;
use App\Services\SettingService;
use App\Services\OrderService;
use App\Services\PromoCodeService;

class CartController extends Controller
{
    use HandlesValidation;
    public function get_user_cart($user_id, $is_saved_for_later = 0, $product_variant_id = '', $store_id = null, $language_code = '')
    {

        $res = [];
        $product_query = Cart::with([
            'productVariant.product.sellerData',
            'productVariant.product.category'
        ])
            ->where('store_id', $store_id)
            ->where('is_saved_for_later', intval($is_saved_for_later))
            ->where('product_type', 'regular')
            ->whereHas('productVariant.product', function ($query) {
                $query->where('status', 1);
            })
            ->whereHas('productVariant', function ($query) {
                $query->where('status', 1);
            })
            ->when($product_variant_id, function ($query) use ($product_variant_id, $user_id) {
                $query->where('product_variant_id', $product_variant_id)
                    ->where('user_id', $user_id)
                    ->where('qty', '>=', 0);
            }, function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->where('qty', '>=', 0);
            })
            ->orderByDesc('id')
            ->get();

        $combo_product_query = Cart::with(['comboProduct.sellerData'])
            ->where('store_id', $store_id)
            ->where('is_saved_for_later', intval($is_saved_for_later))
            ->where('product_type', 'combo')
            ->whereHas('comboProduct', function ($query) {
                $query->where('status', 1);
            })
            ->whereHas('comboProduct.sellerData', function ($query) {
                $query->where('status', 1);
            })
            ->when($product_variant_id, function ($query) use ($product_variant_id, $user_id) {
                $query->where('product_variant_id', $product_variant_id)
                    ->where('user_id', $user_id)
                    ->where('qty', '>=', 0);
            }, function ($query) use ($user_id) {
                $query->where('user_id', $user_id)
                    ->where('qty', '>=', 0);
            })
            ->orderByDesc('id')
            ->get();

        $product_query = $product_query->map(function ($cart) {
            $product = optional(optional($cart->productVariant)->product);
            $cart->id = $product->id;
            $cart->cart_id = $cart->id;
            $cart->cart_product_type = $cart->product_type;
            $cart->product_slug = $product->slug ?? '';
            $cart->image = $product->image ?? '';
            $cart->short_description = $product->short_description ?? '';
            $cart->name = $product->name ?? '';
            $cart->type = $product->type ?? '';
            $cart->seller_id = $product->seller_id ?? '';
            $cart->minimum_order_quantity = $product->minimum_order_quantity ?? 0;
            $cart->minimum_free_delivery_order_qty = $product->minimum_free_delivery_order_qty ?? 0;
            $cart->quantity_step_size = $product->quantity_step_size ?? 1;
            $cart->pickup_location = $product->pickup_location ?? '';
            $cart->total_allowed_quantity = $product->total_allowed_quantity ?? 0;
            $cart->is_prices_inclusive_tax = $product->is_prices_inclusive_tax ?? 0;
            $cart->product_delivery_charge = $product->delivery_charges ?? 0;
            $cart->weight = $cart->productVariant->weight ?? 0;
            $cart->price = $cart->productVariant->price ?? 0;
            $cart->special_price = $cart->productVariant->special_price ?? 0;

            // Handle tax_percentage
            $cart->tax_percentage = Tax::whereRaw("FIND_IN_SET(id, ?)", [$product->tax ?? ''])
                ->pluck('percentage')
                ->implode(',');

            return $cart;
        });


        $combo_product_query = $combo_product_query->map(function ($cart) {
            $combo = $cart->comboProduct;
            $cart->id = $combo->id;
            $cart->cart_id = $cart->id;
            $cart->cart_product_type = $cart->product_type;
            $cart->type = $combo->product_type ?? '';
            $cart->product_delivery_charge = $combo->delivery_charges ?? 0;
            $cart->product_stock = $combo->stock ?? 0;
            $cart->product_availability = $combo->availability ?? '';
            $cart->name = $combo->name ?? '';
            $cart->slug = $combo->slug ?? '';
            $cart->image = $combo->image ?? '';
            $cart->short_description = $combo->short_description ?? '';
            $cart->seller_id = $combo->seller_id ?? '';

            $cart->tax_percentage = Tax::whereRaw("FIND_IN_SET(id, ?)", [$combo->tax ?? ''])
                ->pluck('percentage')
                ->implode(',');

            $cart->tax_title = Tax::whereRaw("FIND_IN_SET(id, ?)", [$combo->tax ?? ''])
                ->pluck('title')
                ->implode(',');

            return $cart;
        });

        $res = $product_query->concat($combo_product_query);

        if (!empty($res)) {
            $res = collect($res)->map(function ($d) use ($language_code) {

                $d->pickup_location = $d->pickup_location ?? '';
                $d->image = app(MediaService::class)->getMediaImageUrl($d->image);
                $d->special_price = ($d->special_price != '' && $d->special_price != null && $d->special_price > 0 && $d->special_price < $d->price) ? $d->special_price : $d->price;
                $percentage = $d->tax_percentage ?? '0';
                if (!empty($percentage)) {
                    $tax_percentage = array_filter(explode(',', $percentage), fn($v) => $v !== '');
                    $total_tax = array_sum(array_map('floatval', $tax_percentage));
                } else {
                    $total_tax = 0;
                }
                $tax_percentage = explode(',', $percentage);
                $total_tax = array_sum($tax_percentage);
                $d->name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $d->id, $language_code);
                $d->short_description = app(TranslationService::class)->getDynamicTranslation(Product::class, 'short_description', $d->id, $language_code);
                if (isset($d->is_prices_inclusive_tax) && $d->is_prices_inclusive_tax == 0) {

                    $price_tax_amount = $d->price * ($total_tax / 100);
                    $special_price_tax_amount = $d->special_price * ($total_tax / 100);
                } else {
                    $price_tax_amount = $d->price - ($d->price * (100 / (100 + $total_tax)));
                    $special_price_tax_amount = $d->special_price - ($d->special_price * (100 / (100 + $total_tax)));
                }

                $price = isset($d->special_price) && $d->special_price != '' && $d->special_price > 0 ? $d->special_price : $d->price;

                if (isset($d->is_prices_inclusive_tax) && $d->is_prices_inclusive_tax == 1) {
                    $tax_amount = $price - ($price * (100 / (100 + $total_tax)));
                } else {
                    $tax_amount = $price * ($total_tax / 100);
                }

                if ((isset($d->is_prices_inclusive_tax) && $d->is_prices_inclusive_tax == 0) || (!isset($d->is_prices_inclusive_tax)) && $total_tax > 0) {
                    $d->price = $d->price + $price_tax_amount;
                    $d->currency_price_data = app(CurrencyService::class)->getPriceCurrency($d->price);
                } else {
                    $d->price = $d->price;
                    $d->currency_price_data = app(CurrencyService::class)->getPriceCurrency($d->price);
                }

                if ((isset($d->is_prices_inclusive_tax) && $d->is_prices_inclusive_tax == 0) || (!isset($d->is_prices_inclusive_tax)) && $total_tax > 0) {
                    $d->special_price = $d->special_price + $special_price_tax_amount;
                    $d->currency_special_price_data = app(CurrencyService::class)->getPriceCurrency($d->special_price);
                } else {
                    $d->special_price = $d->special_price;
                    $d->currency_special_price_data = app(CurrencyService::class)->getPriceCurrency($d->special_price);
                }

                $d->minimum_order_quantity = $d->minimum_order_quantity ?? 1;

                if (isset($d->special_price) && $d->special_price != '' && $d->special_price != null && $d->special_price > 0 && $d->special_price < $d->price) {
                    $d->net_amount = number_format($d->special_price - $special_price_tax_amount, 2);
                    $d->net_amount = str_replace(",", "", $d->net_amount);
                    $d->currency_net_amount_data = app(CurrencyService::class)->getPriceCurrency($d->net_amount);
                } else {
                    $d->net_amount = number_format($d->price - $price_tax_amount, 2);
                    $d->net_amount = str_replace(",", "", $d->net_amount);
                    $d->currency_net_amount_data = app(CurrencyService::class)->getPriceCurrency($d->net_amount);
                }

                $d->tax_percentage = $d->tax_percentage ?? '';
                $d->tax_amount = isset($tax_amount) && $tax_amount != '' ? str_replace(",", "", number_format($tax_amount, 2)) : 0;
                $d->currency_tax_amount_data = app(CurrencyService::class)->getPriceCurrency($d->tax_amount);

                if (isset($d->special_price) && $d->special_price != '' && $d->special_price != null && $d->special_price > 0 && $d->special_price < $d->price) {
                    $d->sub_total = ($d->special_price * $d->qty);
                    $d->currency_sub_total_data = app(CurrencyService::class)->getPriceCurrency($d->sub_total);
                } else {
                    $d->sub_total = ($d->price * $d->qty);
                    $d->currency_sub_total_data = app(CurrencyService::class)->getPriceCurrency($d->sub_total);
                }

                $d->quantity_step_size = $d->quantity_step_size ?? 1;
                $d->total_allowed_quantity = $d->total_allowed_quantity ?? '';
                $d->product_variants = isset($d->product_variant_id) ? app(ProductService::class)->getVariantsValuesById($d->product_variant_id) : '';
                return $d;
            });
        }

        return $res;
    }

    function add_to_cart(Request $request, $check_status = true)
    {

        $store_id = session('store_id');

        $user_id = Auth::user() != '' ? Auth::user()->id : 0;
        if ($user_id) {
        } else {
            $response = [
                'error' => true,
                'message' => 'Please Login first.',
                'code' => 102,
            ];
            return response()->json($response);
        }

        $product_variant_id = $request->input('product_variant_id');

        $qty = $request->input('qty');

        $is_saved_for_later = $request->input('is_saved_for_later');
        $product_type = $request->input('product_type');


        $cart_data = [
            'product_variant_id' => $product_variant_id,
            'qty' => $qty,
            'is_saved_for_later' => $is_saved_for_later,
        ];

        if ($request->input('product_type') != 'combo') {
            $rules = [
                'product_variant_id' => 'required|exists:product_variants,id',
                'qty' => 'required|numeric|min:1',
            ];
        } else {
            $rules = [
                'product_variant_id' => 'required|exists:combo_products,id',
                'qty' => 'required|numeric|min:1',
            ];
        }
        if ($response = $this->HandlesValidation(new \Illuminate\Http\Request($cart_data), $rules)) {
            return $response;
        } else {

            $data = [
                'product_variant_id'
                => $product_variant_id,
                'qty' => $qty,
                'user_id' => $user_id,
                'store_id' => $store_id,
                'is_saved_for_later' => $is_saved_for_later,
                'product_type' => $product_type,
            ];

            $settings = app(SettingService::class)->getSettings('system_settings', true);


            if ($request->input('product_type') != 'combo') {
                if (!isExist(['id' => $product_variant_id], Product_variants::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product Varient not available.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            } else {
                if (!isExist(['id' => $product_variant_id], ComboProduct::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product Varient not available.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
            $settings = json_decode($settings, true);
            $store_details = fetchDetails(Store::class, ['id' => $store_id], '*');
            $is_single_seller_order_system = !$store_details->isEmpty() ? $store_details[0]->is_single_seller_order_system : "";
            //    dd($is_single_seller_order_system);
            if ($settings['single_seller_order_system'] == 1 || $is_single_seller_order_system == 1) {

                if (!app(CartService::class)->isSingleSeller($product_variant_id, $user_id, $product_type, $store_id)) {
                    $response = [
                        'error' => true,
                        'message' => 'Only single seller items are allow in cart.You can remove previous item(s) and add this item.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            //check for digital or phisical product in cart
            if (!app(CartService::class)->isSingleProductType($product_variant_id, $user_id, $product_type, $store_id)) {
                $response = [
                    'error' => true,
                    'message' => 'you can only add either digital product or physical product to cart',
                    'data' => [],
                ];
                return response()->json($response);
            }

            $check_status = ($qty == 0 || $is_saved_for_later == 1) ? false : true;

            $cart_count = app(CartService::class)->getCartCount($user_id, $store_id);


            $is_variant_available_in_cart = app(CartService::class)->isVariantAvailableInCart($product_variant_id, $user_id);

            if (!$is_variant_available_in_cart) {
                if ($cart_count >= $settings['maximum_item_allowed_in_cart']) {
                    $response = [
                        'error' => true,
                        'message' => 'Maximum ' . $settings['maximum_item_allowed_in_cart'] . ' Item(s) Can Be Added Only!',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            $result = app(CartService::class)->addToCart($data, $check_status);

            if (isset($result['error']) && $result['error'] == true) {
                $response = [
                    'error' => true,
                    'message' => $result['message'],
                ];
            } else {
                $cart_count = app(CartService::class)->getCartCount($user_id, $store_id);
                $response = [
                    'error' => false,
                    'message' => 'Product Added to cart successfully',
                    'cart_count' => $cart_count
                ];
            }
            return response()->json($response);
        }
    }

    public function removeFromCart(Request $request)
    {
        $data = [
            'user_id' => $request['user_id'] ?? '',
            'product_variant_id' => $request['product_variant_id'] ?? '',
            'product_type' => $request['product_type'] ?? '',
            'store_id' => $request['store_id'] ?? '',
        ];

        if (app(CartService::class)->removeFromCart($data)) {
            $cart_total = app(CartService::class)->getCartTotal($data['user_id'], false, 0, "", $data['store_id']);
            return response()->json(['error' => false, 'message' => 'Items removed from cart successfully', 'data' => $cart_total], 200);
        } else {
            return response()->json(['error' => true, 'message' => 'Something went wrong'], 400);
        }
    }

    public function cart_sync(Request $request)
    {
        $user_id = auth()->id() ?? 0;
        $store_id = session('store_id');
        $settings = app(SettingService::class)->getSettings('system_settings');
        $settings = json_decode($settings,true);
        $cart_count = app(CartService::class)->getCartCount($user_id, $store_id);
        $pv_ids = implode(",", $request['product_variant_id']);
        $product_types = implode(",", $request['product_type']);
        $isSingleProductType = app(CartService::class)->isSingleProductType($pv_ids, $user_id, $product_types, $store_id);
        if (!$isSingleProductType) {
            $response['error'] = true;
            $response['message'] = 'You Can Only Add Either Digital Product or Physical Product to Cart';
            print_r(json_encode($response));
            return;
        }
        foreach ($request['product_variant_id'] as $key => $variant_id) {
            $is_variant_available_in_cart = app(CartService::class)->isVariantAvailableInCart($variant_id, $user_id);
            if (!$is_variant_available_in_cart) {
                if ($cart_count >= $settings['maximum_item_allowed_in_cart']) {
                    $response['error'] = true;
                    $response['message'] = 'Maximum ' . $settings['maximum_item_allowed_in_cart'] . ' Item(s) Can Be Added Only!';
                    print_r(json_encode($response));
                    return;
                }
            }
            if (is_string($settings)) {
                $settings = json_decode($settings, true);
            } elseif ($settings instanceof \stdClass) {
                $settings = json_decode(json_encode($settings), true);
            }
            if ($settings['single_seller_order_system'] == 1) {

                if (!app(CartService::class)->isSingleSeller($variant_id, $user_id, $request['product_type'][$key])) {
                    $response = [
                        'error' => true,
                        'message' => 'Only single seller items are allow in cart.You can remove privious item(s) and add this item.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
        }
        ;

        $product_variant_id = implode(",", $request['product_variant_id']);
        $qty = implode(",", $request['qty']);
        $product_type = implode(",", $request['product_type']);
        $store_id = implode(",", $request['store_id']);
        $data = [
            'product_variant_id' => $product_variant_id,
            'product_type' => $product_type,
            'qty' => $qty,
            'store_id' => $store_id,
            'user_id' => $user_id,
        ];
        $result = app(CartService::class)->addToCart($data);
        if (isset($result['error']) && $result['error'] == true) {
            $response = [
                'error' => true,
                'message' => $result['message'],
            ];
        } else {
            $cart_count = app(CartService::class)->getCartCount($user_id, $store_id);
            $response = [
                'error' => false,
                'message' => 'Added to cart successfully',
                'cart_count' => $cart_count
            ];
        }
        return response()->json($response);
    }

    public function stripe_payment_intent($order_id, $type)
    {
        // add dynamic data here
        if ($type == 'stripe') {
            $stripe = new Stripe();
            $clientSecret = $stripe->createPaymentIntent([
                'amount' => 10,
                // Add other payment intent data here
            ]);


            return response()->json(['client_secret' => $clientSecret]);
        }


        return response()->json(['error' => 'Invalid payment type or order ID']);
    }

    public function clear_cart()
    {
        // pass here dynamic user id
        $user_id = Auth::user()->id != '' ? Auth::user()->id : 0;

        if ($user_id) {
            $user_id = $user_id;
        } else {
            $response = [
                'error' => true,
                'message' => 'Please Login first.',
                'code' => 102,
            ];
            return response()->json($response);
        }
        deleteDetails(['user_id' => $user_id], Cart::class);
        return response()->json(['message' => 'Data removed successfully'], 200);
    }

    public function manage_cart(Request $request)
    {
        $store_id = session('store_id');
        $user_id = auth()->id() ?? 0;
        $product_variant_id = $request["variant_id"];
        $qty = $request["qty"];
        $address_id = $request["is_saved_for_later"];
        $is_saved_for_later = $request["address_id"];
        $product_type = $request["product_type"];
        $cart_data = [
            'product_variant_id' => $product_variant_id,
            'qty' => $qty,
            'address_id' => $address_id,
            'is_saved_for_later' => $is_saved_for_later,
            'product_type' => $product_type,
        ];

        $rules = [
            'qty' => 'required',
            'address_id' => 'numeric',
            'is_saved_for_later' => 'numeric',
        ];

        if ($product_type == 'regular') {
            $rules = [
                'product_variant_id' => 'required|exists:product_variants,id',
            ];
        } else {
            $rules = [
                'product_variant_id' => 'required|exists:combo_products,id',
            ];
        }

        $validator = Validator::make($cart_data, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors();
            return response()->json(['errors' => $errors->all()], 422);
        } else {
            $user_id = Auth::user()->id != '' ? Auth::user()->id : 0;
            if ($user_id) {
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'code' => 102,
                ];
                return response()->json($response);
            }

            $request = new Request();
            $request->merge(['user_id' => $user_id]);
            $request->merge(['product_variant_id' => $product_variant_id]);
            $request->merge(['qty' => $qty]);
            $request->merge(['product_type' => $product_type]);
            $request->merge(['store_id' => $store_id]);
            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $weight = 0;


            if ($product_type == 'regular') {
                if (!isExist(['id' => $product_variant_id], Product_variants::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product Varient not available.',
                        'data' => [],
                    ];
                    return response()->json($response);

                }
            } else {
                if (!isExist(['id' => $product_variant_id], ComboProduct::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product Varient not available.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }

            }

            $clear_cart = ($request->filled('clear_cart')) ? request('clear_cart') : 0;

            if ($clear_cart == true) {
                if (!app(CartService::class)->removeFromCart(['user_id' => $user_id])) {
                    $response = [
                        'error' => true,
                        'message' => 'Not able to remove existing seller items please try agian later.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            $settings = json_decode($settings, true);
            if ($settings['single_seller_order_system'] == 1) {
                if (!app(CartService::class)->isSingleSeller($product_variant_id, $user_id, $product_type)) {
                    $response = [
                        'error' => true,
                        'message' => 'Only single seller items are allow in cart.You can remove privious item(s) and add this item.',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            //check for digital or phisical product in cart
            if (!app(CartService::class)->isSingleProductType($product_variant_id, $user_id, $product_type)) {
                $response = [
                    'error' => true,
                    'message' => 'you can only add either digital product or physical product to cart',
                    'data' => [],
                ];
                return response()->json($response);
            }

            $shipping_settings = app(SettingService::class)->getSettings('shipping_method', true);
            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $check_status = ($qty == 0 || $is_saved_for_later == 1) ? false : true;

            $cart_count = app(CartService::class)->getCartCount($user_id);


            $is_variant_available_in_cart = app(CartService::class)->isVariantAvailableInCart($product_variant_id, $user_id);

            if (!$is_variant_available_in_cart) {
                if ($cart_count >= $settings['maximum_item_allowed_in_cart']) {
                    $response = [
                        'error' => true,
                        'message' => 'Maximum ' . $settings['maximum_item_allowed_in_cart'] . ' Item(s) Can Be Added Only!',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            if (app(CartService::class)->addToCart($request->toArray(), $check_status)) {

                $res = app(CartService::class)->getCartTotal($user_id, $product_variant_id, $is_saved_for_later, $address_id, $store_id);
                $cart_user_data = $this->get_user_cart($user_id, 0, '', $store_id);

                $product_type = collect(
                    $cart_user_data
                )->pluck('type')->unique()->values()->all();
                $tmpCartUserData = $cart_user_data;

                if (!empty($tmpCartUserData)) {
                    $weight = 0;

                    foreach ($tmpCartUserData as $index => $cartItem) {
                        $cart[$index]['product_qty'] = $cartItem->qty;
                        $cart[$index]['minimum_free_delivery_order_qty'] = $cartItem->minimum_free_delivery_order_qty;
                        $cart[$index]['product_delivery_charge'] = $cartItem->product_delivery_charge;
                        $cart[$index]['product_type'] = $cartItem->product_type;
                        $cart[$index]['type'] = $cartItem->type;

                        $weight += $cartItem->weight * $cartItem->qty;

                        $productData = Product_variants::select('product_id', 'availability')
                            ->where('id', $cartItem->product_variant_id)
                            ->first();

                        if (!empty($productData) && !empty($productData->product_id)) {
                            $proDetails = app(ProductService::class)->fetchProduct(request()->input('user_id'), null, $productData->product_id);

                            if (!empty($proDetails['product'])) {
                                if (trim($proDetails['product'][0]->availability) == 0 && !is_null($proDetails['product'][0]->availability)) {
                                    updateDetails(['is_saved_for_later' => '1'], $cart_user_data[$index]['id'], Cart::class);
                                    unset($cart_user_data[$index]);
                                }

                                if (!empty($proDetails['product'])) {
                                    $cart_user_data[$index]->product_details = $proDetails['product'];
                                } else {
                                    deleteDetails(['id' => $cart_user_data[$index]['id']], Cart::class);
                                    unset($cart_user_data[$index]);
                                    continue;
                                }
                            } else {
                                deleteDetails(['id' => $cart_user_data[$index]['id']], Cart::class);
                                unset($cart_user_data[$index]);
                                continue;
                            }
                        } else {
                            deleteDetails(['id' => $cart_user_data[$index]['id']], Cart::class);
                            unset($cart_user_data[$index]);
                            continue;
                        }
                        $local_user_cart[] = $cart[$index];
                    }
                }

                if (isset($address_id) && !empty($address_id) && isset($res['sub_total']) && !empty($res['sub_total'])) {

                    $delivery_charge = app(DeliveryService::class)->getDeliveryCharge(request('address_id'), $res['sub_total'], $local_user_cart, $store_id);
                    for ($i = 0; $i < count($tmpCartUserData); $i++) {
                        $cart_user_data[$i]->product_delivery_charge = isset($delivery_charge[$i]['delivery_charge']) && !empty($delivery_charge[$i]['delivery_charge']) ? $delivery_charge[$i]['delivery_charge'] : '';
                    }
                }

                $response['error'] = false;
                $response['message'] = 'Cart Updated !';
                $response['cart'] = (isset($cart_user_data) && !empty($cart_user_data)) ? $cart_user_data : [];
                $response['data'] = [
                    'total_quantity' => ($qty == 0) ? '0' : strval($qty),
                    'delivery_charge' => isset($res['delivery_charge']) && !empty($res['delivery_charge']) ? str_replace(",", "", $res['delivery_charge']) : '',
                    'sub_total' => strval($res['sub_total']),
                    'total_items' => (isset($res[0]->total_items)) ? strval($res[0]->total_items) : "0",
                    'tax_percentage' => (isset($res['tax_percentage'])) ? strval($res['tax_percentage']) : "0",
                    'tax_amount' => (isset($res['tax_amount'])) ? strval($res['tax_amount']) : "0",
                    'cart_count' => (isset($res[0]->cart_count)) ? strval($res[0]->cart_count) : "0",
                    'max_items_cart' => $settings['maximum_item_allowed_in_cart'],
                    'overall_amount' => $res['overall_amount'],
                ];
                return response()->json($response);
            }
        }
    }

    public function save_for_later($product_variant_id = '')
    {
        $user_id = Auth::user()->id != '' ? Auth::user()->id : 0;
        $cart_data = [
            'product_variant_id' => $product_variant_id,

        ];

        $rules = [
            'product_variant_id' => 'required|exists:product_variants,id',
        ];
        if ($response = $this->HandlesValidation(new \Illuminate\Http\Request($cart_data), $rules)) {
            return $response;
        } else {
            $query = Cart::where('product_variant_id', intval($product_variant_id))->where('user_id', intval($user_id));
            $cart_data = $query->get();

            $saved_for_later = $cart_data[0]->is_saved_for_later;
            $saved_for_later = $saved_for_later == '1' ? '0' : '1';

            $response = Cart::where('product_variant_id', intval($product_variant_id))
                ->where('user_id', intval($user_id))
                ->update([
                    'is_saved_for_later' => $saved_for_later,
                ]);
            if ($response) {
                return response()->json([
                    'message' => 'Cart Update successfully',
                    'items' => $this->get_user_cart($user_id, 1),
                    'cart_items' => $this->get_user_cart($user_id)
                ], 200);
            } else {
                return response()->json(['message' => 'Something went wrong'], 400);
            }
        }
    }
    public function get_delivery_charge(Request $request)
    {
        $store_id = session('store_id');
        $settings = app(SettingService::class)->getSettings('shipping_method', true);
        $settings = json_decode($settings, true);
        $address_id = $request->input('address_id', null);
        $user_id = Auth::user()->id != '' ? Auth::user()->id : 0;
        if (!empty($address_id)) {

            $address = fetchDetails(Address::class, ['id' => $address_id]);

            $pincode = !$address->isEmpty() ? $address[0]->pincode : "";
            $zipcode = fetchDetails(Zipcode::class, ['zipcode' => $pincode], 'id');
            $zipcode_id = !$zipcode->isEmpty() ? $zipcode[0]->id : "";

            $city = $address[0]->city ?? "";
            $city_id = $address[0]->city_id ?? "";

            $city = $address[0]->city ?? "";
            $city_id = $address[0]->city_id ?? "";

            $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
            $product_availability = false;
            if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
                if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                    $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id);
                } else {
                    $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $zipcode, $zipcode_id, $store_id);
                }
            }
            // $product_availability = checkCartProductsDeliverable($user_id, $zipcode, $zipcode_id);
            $product_not_deliverable = array_filter($product_availability, function ($product) {
                return !$product['is_deliverable'];
            });

            $cart = $this->get_user_cart($user_id);

            $standard_shipping_cart = [];
            $local_shipping_cart = [];

            for ($i = 0; $i < count($cart); $i++) {

                $cart[$i]->delivery_by = $product_availability[$i]['delivery_by'];
                $cart[$i]->is_deliverable = $product_availability[$i]['is_deliverable'];
                if ($cart[$i]->delivery_by == "standard_shipping") {
                    $standard_shipping_cart[] = $cart[$i];
                } else {
                    $local_shipping_cart[] = $cart[$i];
                }
            }




            $error = empty($product_not_deliverable) ? false : true;

            $message = empty($product_not_deliverable) ? "All the products are deliverable on the selected address" : "Some of the item(s) are not deliverable on the selected address. Try changing the address or modify your cart items.";

            $delivery_charge_with_cod = $delivery_charge_without_cod = 0;

            if (!empty($standard_shipping_cart)) {
                $delivery_pincode = Address::where('id', $request->input('address_id'))->value('pincode');
                $parcels = app(ShiprocketService::class)->makeShippingParcels($cart);
                $parcel_details = app(ShiprocketService::class)->checkParcelsDeliverability($parcels, $delivery_pincode);

                $delivery_charge_with_cod = $parcel_details['delivery_charge_with_cod'];
                $delivery_charge_without_cod = $parcel_details['delivery_charge_without_cod'];
                $estimate_date = $parcel_details['estimate_date'];

                $shipping_method = $settings['shiprocket_shipping_method'];
            }

            if (!empty($local_shipping_cart)) {
                $delivery_charge = app(DeliveryService::class)->getDeliveryCharge($request->input('address_id'), $request->input('total'));
                $delivery_charge_with_cod += $delivery_charge;
                $delivery_charge_without_cod += $delivery_charge;
            }

            $data = $cart;
            $availability_data = $product_availability;
        } else {
            $error = true;
            $message = "Please select an address.";
        }

        return response()->json([
            'response_error' => $error,
            'deliverable_message' => $message,
            'delivery_charge_with_cod' => $delivery_charge_with_cod ?? 0,
            'delivery_charge_without_cod' => $delivery_charge_without_cod ?? 0,
            'estimate_date' => $estimate_date ?? "",
            'shipping_method' => $shipping_method ?? "",
            'data' => $data ?? [],
            'availability_data' => $availability_data ?? [],
        ]);
    }

    public function pre_payment_setup(Request $request)
    {

        $user_id = Auth::user()->id ?? 0;
        $store_id = session('store_id');
        if ($user_id == 0) {
            return response()->json([
                'error' => true,
                'message' => "Please login First",
            ]);
        }
        $cart = app(CartService::class)->getCartTotal($user_id, false, '0', $request['address_id'], $store_id);
        $user = fetchDetails(User::class, ['id' => $user_id], ['username', 'email', 'mobile', 'balance']);
        $product_name = [];
        $check_single_product_type = [];
        $check_combo_single_product_type = [];
        foreach ($cart['cart_items'] as $item) {
            array_push($product_name, $item->product->name);
            if ($item->cart_product_type == 'combo') {
                array_push($check_combo_single_product_type, $item->product->type);
            } else {
                array_push($check_single_product_type, $item->product->type);
            }
        }
        $is_single_product_type = array_merge($check_single_product_type, $check_combo_single_product_type);
        // CHECK FOR REGULAR PRODUCT
        $hasDigitalProduct = in_array('digital_product', $is_single_product_type);
        $hasSimpleOrVariableProduct = in_array('simple_product', $is_single_product_type) || in_array('variable_product', $is_single_product_type) || in_array('physical_product', $is_single_product_type);
        if ($hasDigitalProduct && $hasSimpleOrVariableProduct) {
            $response = [
                'error' => true,
                'message' => "It is not possible to order digital and physical items together.",
            ];
            return response()->json($response);
        }
        $product_name = implode(",", $product_name);
        $walletBalance = ($user) != '' ? $user[0]->balance : 0;
        $overall_amount = $cart['overall_amount'];

        // Check if wallet is used and deduct the balance
        if ($request['wallet_used'] == 1 && $walletBalance > 0) {
            $overall_amount -= $walletBalance;
        }
        $settings = app(SettingService::class)->getSettings('system_settings', true);
        $settings = json_decode($settings, true);

        if ($request['product_type'] != 'digital_product') {
            $address = fetchDetails(Address::class, ['id' => $request->address_id]);

            $pincode = $address[0]->pincode ?? "";
            $zipcode = fetchDetails(Zipcode::class, ['zipcode' => $pincode], 'id');
            $zipcode_id = (!empty($zipcode) ? $zipcode[0]->id : "");


            $city = $address[0]->city ?? "";
            $city_id = $address[0]->city_id ?? "";

            $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
            $product_delivarable = false;
            if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
                if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                    $product_delivarable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id);
                } else {
                    $product_delivarable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $pincode, $zipcode_id, $store_id);
                }
            }

            if ($product_delivarable == false || $product_delivarable[0]['is_deliverable'] == false) {
                $response = [
                    'error' => true,
                    'message' => "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.",
                    'data' => $product_delivarable,
                ];
                return response()->json($response);
            }
        }
        if (!empty($request['promo_code_id'])) {
            $validate = app(abstract: PromoCodeService::class)->validatePromoCode($request['promo_code_id'], $user_id, $cart['total_arr'], 1)->original;
            if ($validate['error'] == true) {
                return response()->json([
                    'error' => true,
                    'message' => $validate['message'],
                ]);
            } else {
                $overall_amount -= $validate['data'][0]->final_discount;
            }
        }
        // Payment method specific logic
        if ($request['payment_method'] == 'razorpay') {

            $razorpay = new Razorpay();
            $order = $razorpay->create_order(($overall_amount));

            if (!isset($order['error'])) {
                return response()->json([
                    'error' => false,
                    'order_id' => $order['id'],
                    'data' => $order,
                    'response_message' => 'Client Secret Get Successfully.'
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'response_message' => $order['error']['description']
                ]);
            }
        } elseif ($request['payment_method'] == "midtrans" || $request['payment_method'] == "Midtrans") {
            $order_id = "mdtrns-" . $user_id . "-" . time() . "-" . rand("100", "999");

            $midtrans = new Midtrans();
            $order = $midtrans->create_transaction($order_id, $overall_amount);
            $order['body'] = (isset($order['body']) && !empty($order['body'])) ? json_decode($order['body'], 1) : "";


            if (!empty($order['body'])) {
                return response()->json([
                    'error' => false,
                    'order_id' => $order_id,
                    'token' => $order['body']['token'] ?? '',
                    'redirect_url' => $order['body']['redirect_url'],
                    'response_message' => 'Transaction Token generated successfully.',
                    'overall_amount' => $overall_amount,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'details' => $order,
                    'response_message' => "Oops! Token couldn't be generated! check your configurations!",
                    'overall_amount' => $overall_amount
                ]);
            }
        } elseif ($request['payment_method'] == "paystack" || $request['payment_method'] == "paypal" || $request['payment_method'] == "stripe") {
            return response()->json([
                'error' => false,
                'final_amount' => number_format((float) ($overall_amount), 2, ".", ""),
                'product_name' => $product_name
            ]);
        }
    }

    public function place_order(Request $request, TransactionController $transactionController)
    {
        if ($request->has('res')) {
            $res = $request->input('res');
            $request = new Request($res);
            $request['final_total'] = $request['amount'];
        }
        $store_id = session('store_id') ?? '';
        $user_id = Auth::user()->id ?? "";
        if ($user_id == "") {
            $response = [
                'error' => true,
                'message' => 'Please Login first.',
                'code' => 102,
            ];
            return response()->json($response);
        }
        $validator = Validator::make(
            $request->all(),
            [
                'mobile' => 'nullable|numeric',
                'promo_code' => 'nullable',
                'order_note' => 'nullable',
                'is_wallet_used' => 'required|numeric',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'delivery_date' => 'nullable',
                'delivery_time' => 'nullable',
            ]
        );
        if ($request['is_wallet_used'] != 1 && $request['final_total'] != 0) {
            $validator = Validator::make(
                $request->all(),
                [
                    'payment_method' => 'required',
                ]
            );
        }
        if ($validator->fails()) {
            $response = [
                'error' => true,
                'message' => $validator->errors()->all(),
                'code' => 102,
            ];
            return response()->json($response);
        }

        if (isset($request['product_type']) && $request['product_type'] != 'digital_product') {
            $validator = Validator::make($request->all(), [
                'selected_address_id' => 'required|exists:addresses,id',
            ]);

            if ($validator->fails()) {
                $response = [
                    'error' => true,
                    'message' => $validator->errors()->all(),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }
        if ($request['product_type'] != 'digital_product') {
            $user_cart_data = app(CartService::class)->getCartTotal($user_id, false, 0, "", $store_id);
        } else {
            $user_cart_data = app(CartService::class)->getCartTotal($user_id, false, 0, $request['selected_address_id'], $store_id);
        }
        // dd($user_cart_data['cart_items']);
        if (count($user_cart_data) <= 0) {
            $response = [
                'error' => true,
                'message' => 'Cart Is Empty',
            ];
            return response()->json($response);
        }
        $product_variant_ids = [];
        $cart_product_types = [];
        $quantity = [];
        $check_combo_single_product_type = [];
        $check_single_product_type = [];
        foreach ($user_cart_data['cart_items'] as $cart_items) {
            if ($cart_items->cart_product_type == 'combo') {
                array_push($check_combo_single_product_type, $cart_items->product->type);
                array_push($product_variant_ids, $cart_items['product_variant_id']);
            } else {
                array_push($check_single_product_type, $cart_items->product->type);
                array_push($product_variant_ids, $cart_items['product_variants'][0]['id']);
            }
            array_push($cart_product_types, $cart_items->cart_product_type);
            array_push($quantity, $cart_items['qty']);
        }
        // dd($cart_items['product_variants']);
        $is_single_product_type = array_merge($check_single_product_type, $check_combo_single_product_type);
        // CHECK FOR REGULAR PRODUCT
        $hasDigitalProduct = in_array('digital_product', $is_single_product_type);
        $hasSimpleOrVariableProduct = in_array('simple_product', $is_single_product_type) || in_array('variable_product', $is_single_product_type) || in_array('physical_product', $is_single_product_type);
        if ($hasDigitalProduct && $hasSimpleOrVariableProduct) {
            $response = [
                'error' => true,
                'message' => "It is not possible to order digital and physical items together.",
            ];
            return response()->json($response);
        }

        $productType = $user_cart_data['cart_items'][0]['product']['type'];
        $downloadAllowed = $user_cart_data['cart_items'][0]['product']['download_allowed'];
        if ($downloadAllowed && $productType == 'digital_product') {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            if ($validator->fails()) {
                $response = [
                    'error' => true,
                    'message' => $validator->errors()->all(),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }
        if ($request['payment_method'] == 'razorpay') {
            $validator = Validator::make($request->all(), [
                'razorpay_payment_id' => 'required',
            ]);
            if ($validator->fails()) {
                $response = [
                    'error' => true,

                    'message' => $validator->errors()->all(),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }
        if ($request['payment_method'] == 'paystack') {

            $validator = Validator::make($request->all(), [
                'paystack_reference' => 'required',
            ]);
            if ($validator->fails()) {
                $response = [
                    'error' => true,

                    'message' => $validator->errors()->all(),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }
        if ($request['is_wallet_used'] == '1') {
            $validator = Validator::make($request->all(), [
                'wallet_balance_used' => 'required|numeric',
            ]);
            if ($validator->fails()) {
                $response = [
                    'error' => true,
                    'message' => $validator->errors()->all(),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }

        $system_settings = app(SettingService::class)->getSettings('system_settings', true);
        $system_settings = json_decode($system_settings, true);

        $currency = isset($system_settings['currency']) && !empty($system_settings['currency']) ? $system_settings['currency'] : '';
        $total = $request['final_total'] + $request['wallet_balance_used'];
        $order_payment_currency_code = fetchDetails(Currency::class, ['symbol' => $request['currency_code']], 'code');
        $order_payment_currency_code = isset($order_payment_currency_code) && !empty($order_payment_currency_code) ? $order_payment_currency_code[0]->code : 'USD';


        if (isset($system_settings['minimum_cart_amount']) && !empty($system_settings['minimum_cart_amount'])) {
            if ($total < $system_settings['minimum_cart_amount']) {
                $response = [
                    'error' => true,
                    'message' => 'Total amount should be greater or equal to ' . $currency . $system_settings['minimum_cart_amount'] . ' total is ' . $currency . $request->input('total', 0),
                    'code' => 102,
                ];
                return response()->json($response);
            }
        }

        $request['order_note'] =
            !empty($request['order_note']) ? $request['order_note'] : null;

        /* Checking for product availability */

        $address = fetchDetails(Address::class, ['id' => $request['selected_address_id']]);

        $pincode = $address[0]->pincode ?? "";
        $zipcode = fetchDetails(Zipcode::class, ['zipcode' => $pincode], 'id');
        $zipcode_id = (!empty($zipcode) ? $zipcode[0]->id : "");

        $city = $address[0]->city ?? "";
        $city_id = $address[0]->city_id ?? "";

        $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
        $product_availability = false;
        if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
            if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id);
            } else {
                $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $pincode, $zipcode_id, $store_id);
            }
        }
        $promo_code_id = "";
        if ($request['promo_set'] == 1) {
            $promo_code_id = fetchDetails(Promocode::class, ['promo_code' => $request['promo_code']]);
        }

        if (!empty($product_availability) && $productType != "digital_product") {
            if ($product_availability == false || $product_availability[0]['is_deliverable'] == false) {
                $response = [
                    'error' => true,
                    'message' => "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.",
                    'data' => $product_availability,
                ];
                return response()->json($response);
            } else {
                $data['is_delivery_charge_returnable'] = isset($request['delivery_charge']) && !empty($request['delivery_charge']) && $request['delivery_charge'] > 0 ? 1 : 0;
                $data = [
                    'product_variant_id' => implode(",", $product_variant_ids),
                    'cart_product_type' => implode(",", $cart_product_types),
                    'quantity' => implode(",", $quantity),
                    'store_id' => $store_id,
                    'delivery_charge' => $request['delivery_charge'],
                    'discount' => $request['discount'],
                    'promo_code_id' => isset($request['promo-code-id']) && !empty($request['promo-code-id']) ? $request['promo-code-id'] : $promo_code_id[0]->id ?? "",
                    'promo_code' => $request['promo_code'],
                    'user_id' => $user_id,
                    'is_wallet_used' => $request['is_wallet_used'],
                    'wallet_balance_used' => $request['wallet_balance_used'],
                    'mobile' => $request['address-mobile'],
                    'email' => $request['email'] ?? "",
                    'address_id' => $request['selected_address_id'] ?? "",
                    'delivery_type' => isset($product_availability) && !empty($product_availability) ? $product_availability[0]['delivery_by'] : '',
                    'delivery_time' => $request['delivery_time'] ?? "",
                    'delivery_date' => $request['delivery_date'] ?? "",
                    'longitude' => $request['longitude'] ?? "",
                    'latitude' => $request['latitude'] ?? "",
                    'order_note' => $request['order_note'] ?? "",
                    'payment_method' => $request['payment_method'] ?? "",
                    'product_type' => implode(",", $cart_product_types) ?? "",
                    'order_payment_currency_code' => $order_payment_currency_code ?? "",
                    'razorpay_payment_id' => $request['razorpay_payment_id'] ?? "",
                    'status' => $request['status'] ?? "awaiting"
                ];

                if ($request['payment_method'] == "razorpay") {

                    if (!app(OrderService::class)->verifyPaymentTransaction($data['razorpay_payment_id'], 'razorpay')) {
                        $response = [
                            'error' => true,
                            'message' => 'Invalid Razorpay Payment Transaction',
                            'code' => 102,
                        ];
                        return response()->json($response);
                    }
                } elseif ($request['payment_method'] == "paystack") {
                    $paystack = new Paystack();
                    $payment = $paystack->verify_transaction($request['paystack_reference']);
                    if (!empty($payment)) {
                        $payment = json_decode($payment, true);
                        if (isset($payment['data']['status']) && $payment['data']['status'] == 'success') {
                            $response['error'] = false;
                            $response['message'] = "Payment is successful";
                            $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                            $response['data'] = $payment;
                        } elseif (isset($payment['data']['status']) && $payment['data']['status'] != 'success') {
                            $response['error'] = true;
                            $response['message'] = "Payment is " . ucwords($payment['data']['status']) . "! ";
                            $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                            $response['data'] = $payment;
                            return $response;
                        } else {
                            $response['error'] = true;
                            $response['message'] = "Payment is unsuccessful! ";
                            $response['amount'] = (isset($payment['data']['amount'])) ? $payment['data']['amount'] / 100 : 0;
                            $response['data'] = $payment;
                            return $response;
                        }
                    } else {
                        $response['error'] = true;
                        $response['message'] = "Payment not found by the transaction ID!";
                        $response['amount'] = 0;
                        $response['data'] = [];
                        return $response;
                    }
                }
                $res = app(OrderService::class)->placeOrder($data, 1);
                if (!empty($res)) {
                    if ($res['error'] == true) {
                        $response = [
                            'error' => true,
                            'message' => $res['message'],
                        ];
                        return response()->json($response);
                    }
                    if ($data['payment_method'] == "bank_transfer" || $data['payment_method'] == "direct_bank_transfer" || $data['payment_method'] == 'stripe' || $data['payment_method'] == 'phonepe' || $data['payment_method'] == 'paypal' || $data['payment_method'] == 'paystack' || $data['payment_method'] == 'razorpay') {
                        if ($data['payment_method'] == 'phonepe') {
                            $transaction_id = $request['phonepe_transaction_id'];
                        } elseif ($data['payment_method'] == 'paypal') {
                            $transaction_id = $request['paypal_transaction_id'];
                        } elseif ($data['payment_method'] == 'paystack') {
                            $transaction_id = $request['paystack_reference'];
                            $status = 'success';
                            $message = 'Payment Successfully';
                        } elseif ($data['payment_method'] == 'stripe') {
                            $transaction_id = $request['stripe_payment_id'];
                            $status = 'success';
                            $message = 'Payment Successfully';
                        } elseif ($data['payment_method'] == 'razorpay') {
                            $transaction_id = $request['razorpay_payment_id'];
                            $status = 'success';
                            $message = 'Payment Successfully';
                        }
                        $data = new Request([
                            'status' => $status ?? "awaiting",
                            'txn_id' => $transaction_id ?? null,
                            'message' => $message ?? 'Payment Is Pending',
                            'order_id' => $res['order_id'],
                            'user_id' => $user_id,
                            'type' => $data['payment_method'],
                            'amount' => $total,
                        ]);
                        $transactionController->store($data);
                    }
                }
                if (isset($res->original) && !empty($res->original)) {
                    return response()->json($res->original);
                } else {
                    return response()->json($res);
                }
            }
        } else {


            if (
                $request['payment_method'] == "razorpay"
            ) {
                if (!app(OrderService::class)->verifyPaymentTransaction($request['razorpay_payment_id'], 'razorpay')) {
                    $response = [
                        'error' => true,
                        'message' => 'Invalid Razorpay Payment Transaction',
                        'code' => 102,
                    ];
                    return response()->json($response);
                }
            } elseif ($request['payment_method'] == "paystack") {

                $transfer = app(OrderService::class)->verifyPaymentTransaction($request['paystack_reference'], 'paystack');
                if (isset($transfer['data']['status']) && $transfer['data']['status']) {
                    if (isset($transfer['data']['data']['status']) && $transfer['data']['data']['status'] != "success") {
                        $response = [
                            'error' => true,
                            'txn_id' => 'Invalid Paystack Transaction.',
                            'data' => [],
                        ];
                        return response()->json($response);
                    }
                } else {
                    $response = [
                        'error' => true,
                        'txn_id' => 'Error While Fetching the Order Details.Contact Admin ASAP.',
                        'data' => $transfer,
                        'code' => 200,
                    ];
                    return response()->json($response);
                }
                $response = [
                    'txn_id' => $request['paystack_reference'],
                    'message' => 'Order Placed Successfully',
                    'status' => "success",
                ];
            }

            $data = [
                'product_variant_id' => implode(",", $product_variant_ids),
                'cart_product_type' => implode(",", $cart_product_types),
                'quantity' => implode(",", $quantity),
                'store_id' => $store_id,
                'delivery_charge' => $request['delivery_charge'],
                'discount' => $request['discount'],
                'promo_code_id' => $promo_code_id[0]->id ?? "",
                'promo_code' => $request['promo_code'],
                'user_id' => $user_id,
                'is_wallet_used' => $request['is_wallet_used'],
                'wallet_balance_used' => $request['wallet_balance_used'],
                'mobile' => $request['address-mobile'],
                'email' => $request['email'] ?? "",
                'address_id' => $request['selected_address_id'] ?? "",
                'delivery_time' => $request['delivery_time'] ?? "",
                'delivery_date' => $request['delivery_date'] ?? "",
                'longitude' => $request['longitude'] ?? "",
                'latitude' => $request['latitude'] ?? "",
                'order_note' => $request['order_note'] ?? "",
                'payment_method' => $request['payment_method'] ?? "",
                'product_type' => implode(",", $cart_product_types) ?? "",
                'order_payment_currency_code' => $order_payment_currency_code,
                'razorpay_payment_id' => $request['razorpay_payment_id'] ?? ""
            ];
            $res = app(OrderService::class)->placeOrder($data, 1);

            if (!empty($res)) {
                if ($data['payment_method'] == "bank_transfer" || $data['payment_method'] == 'direct_bank_transfer' || $data['payment_method'] == 'stripe' || $data['payment_method'] == 'phonepe' || $data['payment_method'] == 'paypal' || $data['payment_method'] == 'paystack' || $data['payment_method'] == 'razorpay') {
                    if ($data['payment_method'] == 'phonepe') {
                        $transaction_id = $request['phonepe_transaction_id'];
                    } elseif ($data['payment_method'] == 'paypal') {
                        $transaction_id = $request['paypal_transaction_id'];
                    } elseif ($data['payment_method'] == 'paystack') {
                        $transaction_id = $request['paystack_reference'];
                        $status = 'success';
                        $message = 'Payment Successfully';
                    } elseif ($data['payment_method'] == 'stripe') {
                        $transaction_id = $request['stripe_payment_id'];
                        $status = 'success';
                        $message = 'Payment Successfully';
                    } elseif ($data['payment_method'] == 'stripe') {
                        $transaction_id = $request['razorpay_payment_id'];
                        $status = 'success';
                        $message = 'Payment Successfully';
                    }


                    $data = new Request([
                        'status' => $status ?? "awaiting",
                        'txn_id' => $transaction_id ?? null,
                        'message' => $message ?? 'Payment Is Pending',
                        'order_id' => $res['order_id'],
                        'user_id' => $user_id,
                        'type' => $data['payment_method'],
                        'amount' => $total,
                    ]);

                    $transactionController->store($data);
                }
            }
            return response()->json($res);
        }
    }
}
