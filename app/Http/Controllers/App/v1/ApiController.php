<?php

namespace App\Http\Controllers\App\v1;

use App\Http\Controllers\Admin\AddressController;
use App\Http\Controllers\Admin\AreaController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ComboProductRatingController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\admin\OrderController;
use App\Http\Controllers\Admin\ProductRatingController;
use App\Http\Controllers\Admin\PromoCodeController;
use App\Http\Controllers\Admin\SellerController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\CartController;
use App\Libraries\Paypal;
use App\Libraries\Paystack;
use App\Libraries\Phonepe;
use App\Libraries\Razorpay;
use App\Libraries\Shiprocket;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\CategorySliders;
use App\Models\City;
use App\Models\ComboProduct;
use App\Models\ComboProductFaq;
use App\Models\Currency;
use App\Models\Favorite;
use App\Models\Language;
use App\Models\Media;
use App\Models\Offer;
use App\Models\OfferSliders;
use App\Models\Order;
use App\Models\OrderBankTransfers;
use App\Models\OrderItems;
use App\Models\Otps;
use App\Models\PaymentRequest;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\ProductFaq;
use App\Models\Role;
use App\Models\SearchHistory;
use App\Models\Section;
use App\Models\SellerStore;
use App\Models\Slider;
use App\Models\StorageType;
use App\Models\Store;
use App\Models\Brand;
use App\Models\Tax;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketType;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFcm;
use App\Models\Zipcode;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\TranslationService;
use App\Traits\HandlesValidation;
use App\Services\ProductService;
use App\Services\ComboProductService;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\MediaService;
use App\Services\ShiprocketService;
use App\Services\CurrencyService;
use App\Services\SettingService;
use App\Services\OrderService;
use App\Services\WalletService;
use App\Services\PromoCodeService;
class ApiController extends Controller
{
    use HandlesValidation;
    /*
---------------------------------------------------------------------------
Defined Methods:-
---------------------------------------------------------------------------

    1. user-registration
        - login
        - update_fcm
        - reset_password
        - get_login_identity
        - verify_user
        - register_user
    2. get_categories
    3. get_cities
    4. get_products
    5. get_slider_images
    6. get_settings
    7. update_user
    8. delete_user

    9. favorites
        -add_to_favorites
        -remove_from_favorites
        -get_favorites

    10. user_addresses
        -add_address
        -update_address
        -delete_address
        -get_address

    11. get_combo_products
    12. get_user_cart
    13. get_sections
    14. get_zipcode_by_city_id
    15. validate_promo_code
    16. place_order
    17. remove_from_cart
    18. manage_cart
    19. clear_cart
    20. get_orders
    21. update_order_item_status
    22. get_faqs
    23. get_offer_images
    24. get_ticket_types
    25. add_ticket
    26. edit_ticket
    27. get_tickets
    28. get_messages
    29. is_product_delivarable
    30. check_cart_products_delivarable
    31. get_sellers
    32. get_promo_codes
    33. get_stores
    34. get_brands
    35. sign_up
    36. delete_social_account
    37. add_product_faqs
    38. get_product_faqs
    39. send_message
    40. get_zipcodes
    41. update_order_status
    42. delete_order
    43. validate_refer_code
    44. get_notifications
    45. add_transaction
    46. transactions
    47. set_product_rating
    48. get_product_rating
    49. delete_product_rating
    50. check_shiprocket_serviceability
    51. send_withdrawal_request
    52. get_withdrawal_request
    53. send_bank_transfer_proof
    54. download_link_hash
    55. get_offers_sliders
    56. get_categories_sliders
    57. set_combo_product_rating
    58. get_combo_product_rating
    59. delete_combo_product_rating

---------------------------------------------------------------------------
---------------------------------------------------------------------------

*/
    public function login(Request $request)
    {
        /*
            mobile : 9876543210
            pass : 12345678
        */
        $rules = [
            'mobile' => 'required|numeric',
            'password' => 'required',
        ];

        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $credentials = $request->only('mobile', 'password');

            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $token = $user->createToken('authToken')->plainTextToken;
                $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user->id], 'fcm_id');

                $fcm_ids_array = array_map(function ($item) {
                    return $item->fcm_id;
                }, $fcm_ids->all());
                $user_data = [
                    'id' => $user->id ?? '',
                    'ip_address' => $user->ip_address ?? '',
                    'username' => $user->username ?? '',
                    'email' => $user->email ?? '',
                    'mobile' => $user->mobile ?? '',
                    'image' => app(MediaService::class)->getMediaImageUrl($user->image, 'USER_IMG_PATH'),
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
                    'is_notification_on' => $user->is_notification_on ?? '',
                ];
                return response()->json([
                    'error' => false,
                    'message' => 'User Logged in successfully',
                    'language_message_key' => 'user_logged_in_successfully',
                    'token' => $token,
                    'user' => $user_data,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid credentials',
                    'language_message_key' => 'invalid_credentials',
                ], 401);
            }
        }
    }

    public function get_categories(CategoryController $categoryController, Request $request)
    {
        /*
            store_id:3
            id:15               // optional
            limit:25            // { default - 25 } optional
            offset:0            // { default - 0 } optional
            sort:               id / name // { default -row_id } optional
            order:DESC/ASC      // { default - ASC } optional
            has_child_or_item:false { default - true}  optional
                                */
        $rules = [
            'id' => 'numeric|exists:categories,id',
            'limit' => 'numeric',
            'offset' => 'numeric',
            'store_id' => 'required|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {

            $store_id = $request->input('store_id') ? (int) $request->input('store_id') : '';

            $id = $request->filled('id') ? (int) $request->input('id') : '';
            $ids = $request->filled('ids') ? $request->input('ids') : '';
            $search = $request->filled('search') ? trim($request->input('search')) : '';
            $limit = $request->filled('limit') ? (int) $request->input('limit') : 25;
            $offset = $request->filled('offset') ? (int) $request->input('offset') : 0;
            $sort = $request->filled('sort') ? $request->input('sort') : 'row_order';
            $order = $request->filled('order') ? $request->input('order') : 'ASC';
            $has_child_or_item = $request->filled('has_child_or_item') ? $request->input('has_child_or_item') : 'true';

            $response = ['message' => 'Category(s) retrieved successfully'];
            $language_code = $request->attributes->get('language_code');
            $cat_res = $categoryController->get_categories($id, $limit, $offset, $sort, $order, $has_child_or_item, '', '', '', $store_id, $search, $ids, $language_code);
            // dd($cat_res);
            $popular_categories = $categoryController->get_categories(NULL, "", "", 'clicks', 'DESC', 'false', "", "", "", $store_id, "", "", $language_code);

            return response()->json([
                'error' => $cat_res->original['categories']->isEmpty() ? true : false,
                'total' => $cat_res->original['total'],
                'message' => $cat_res->original['categories']->isEmpty() ? 'Category does not exist' : 'Category retrieved successfully',
                'language_message_key' => $cat_res->original['categories']->isEmpty() ? 'categories_does_not_exist' : 'categories_retrived_successfully',
                'data' => $cat_res->original['categories'],
                'popular_categories' => $popular_categories->original['categories'],
            ]);
        }
    }

    public function get_cities(AreaController $areaController, Request $request)
    {
        /*
           sort:               // { c.name / c.id } optional
           order:DESC/ASC      // { default - ASC } optional
           limit:25            // { default - 25 } optional
           offset:0            // { default - 0 } optional
           search:value        // {optional}
       */
        $rules = [
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $limit = $request->filled('limit') ? (int) $request->input('limit') : 25;
            $offset = $request->filled('offset') ? (int) $request->input('offset') : 0;
            $sort = $request->filled('sort') ? $request->input('sort') : 'name';
            $order = $request->filled('order') ? $request->input('order') : 'ASC';
            $search = $request->filled('search') ? trim($request->input('search')) : '';
            $language_code = $request->attributes->get('language_code');
            $city_res = $areaController->getCitiesList($sort, $order, $search, $limit, $offset, $language_code);
            return response()->json($city_res->original);
        }
    }

    public function get_products(Request $request)
    {
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'id' => 'sometimes|numeric|exists:products,id',
            'product_ids' => 'sometimes|string',
            'product_variant_ids' => 'sometimes|string',
            'search' => 'sometimes|string',
            'category_id' => 'sometimes|exists:categories,id',
            'attribute_value_ids' => 'sometimes',
            'sort' => 'sometimes|string',
            'limit' => 'sometimes|numeric',
            'offset' => 'sometimes|numeric',
            'order' => 'sometimes|string|alpha',
            'is_similar_products' => 'sometimes|numeric',
            'top_rated_product' => 'sometimes|numeric',
            'min_price' => 'sometimes|numeric|lte:max_price',
            'max_price' => 'sometimes|numeric|gte:min_price',
            'discount' => 'sometimes|numeric',
            'zipcode' => 'sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $tags = [];
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->filled('order') ? $request->input('order') : 'DESC';
            // dd($order);
            $sort = $request->filled('sort') ? $request->input('sort') : 'products.id';
            if ($sort == 'pv.price') {
                $sort = "pv.price";
            }
            $language_code = $request->attributes->get('language_code');
            $store_id = $request->input('store_id') ? (int) $request->input('store_id') : '';
            $is_detailed_data = $request->input('is_detailed_data') ? $request->input('is_detailed_data') : 0;
            $seller_id = $request->filled('seller_id') ? $request->input('seller_id') : null;
            $filters['search'] = $request->filled('search') ? trim($request->input('search')) : '';
            $filters['tags'] = $request->input('tags', '');
            $filters['rating'] = $request->input('rating', '');
            $filters['attribute_value_ids'] = $request->filled('attribute_value_ids') ? $request->input('attribute_value_ids') : null;
            $filters['is_similar_products'] = $request->filled('is_similar_products') ? $request->input('is_similar_products') : null;
            $filters['most_popular_products'] = $request->filled('most_popular_products') ? $request->input('most_popular_products') : '';
            $filters['discount'] = $request->filled('discount') ? $request->input('discount', 0) : 0;
            $filters['product_type'] = $request->input('top_rated_product', 0) == 1 ? 'top_rated_product_including_all_products' : $request->input('product_type');
            $filters['minimum_price'] = $request->filled('minimum_price') ? $request->input('minimum_price') : '';
            $filters['maximum_price'] = $request->filled('maximum_price') ? $request->input('maximum_price') : '';
            // $filters['show_only_active_products'] = 1;
            $zipcode = $request->filled('zipcode') ? $request->input('zipcode') : 0;
            $type = $request->has('type') ? $request->input('type') : '';
            //find product according to zipcode
            if ($request->filled('zipcode')) {
                $is_pincode = Zipcode::where('zipcode', $zipcode)->exists();
                if ($is_pincode) {
                    $zipcode_id = Zipcode::where('zipcode', $zipcode)->firstOrFail()->id;

                    $zipcode = $zipcode_id;
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'Products Not Found !',
                        'language_message_key' => 'products_not_found'
                    ], 200);
                }
            }
            $category_id = $request->input('category_id', null);
            $brand_id = $request->input('brand_id', null);
            $product_id = $request->input('id', null);
            $user_id = $request->input('user_id', null);
            $product_ids = $request->input('product_ids', null);
            $product_variant_ids = $request->filled('product_variant_ids') ? $request->input('product_variant_ids') : null;

            if (!is_null($product_ids)) {
                $product_id = explode(",", $product_ids);
            }
            if (!is_null($category_id)) {
                $category_id = explode(",", $category_id);
            }
            if (!is_null($brand_id)) {
                $brand_id = explode(",", $brand_id);
            }
            if (!is_null($product_variant_ids)) {
                $filters['product_variant_ids'] = explode(",", $product_variant_ids);
            }

            //fetch product using filters
            $products = app(ProductService::class)->fetchProduct($user_id, (isset($filters)) ? $filters : null, $product_id, $category_id, $limit, $offset, $sort, $order, null, $zipcode, $seller_id, $brand_id, $store_id, $is_detailed_data, $type, 0, $language_code);
            //    dd($products);
            foreach ($products['product'] as $product) {
                if (!empty($product->tags)) {
                    $tags = array_values(array_unique(array_merge($tags, $product->tags)));
                }
            }
            if (!empty($products['product'])) {

                $filtered_brand_ids = array_filter($products['brand_ids'], function ($value) {
                    return !empty($value);
                });
                $brand_ids = implode(',', $filtered_brand_ids);
                $response = [
                    'error' => false,
                    'message' => 'Products retrieved successfully!',
                    'language_message_key' => 'products_retrived_successfully',
                    'min_price' => isset($products['min_price']) && !empty($products['min_price']) ? strval($products['min_price']) : '0',
                    'max_price' => isset($products['max_price']) && !empty($products['max_price']) ? strval($products['max_price']) : '0',
                    'category_ids' => isset($products['category_ids']) && !empty($products['category_ids']) ? implode(',', $products['category_ids']) : '',
                    'brand_ids' => isset($products['brand_ids']) && !empty($products['brand_ids']) ? $brand_ids : '',
                    'search' => $filters['search'],
                    'filters' => isset($products['filters']) && !empty($products['filters']) ? $products['filters'] : [],
                    'tags' => !empty($tags) ? $tags : [],
                    'total' => isset($products['total']) ? strval($products['total']) : '',
                    'offset' => $offset,
                    'data' => $products['product'],
                ];
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Products Not Found !',
                    'language_message_key' => 'products_not_found',
                    'data' => [],
                ], 200);
            }

            return response()->json($response);
        }
    }
    public function get_combo_products(Request $request)
    {
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'id' => 'sometimes|numeric|exists:combo_products,id',
            'product_ids' => 'sometimes|string',
            'search' => 'sometimes|string',
            'attribute_value_ids' => 'sometimes|string',
            'sort' => 'sometimes|string',
            'limit' => 'sometimes|numeric',
            'offset' => 'sometimes|numeric',
            'order' => 'sometimes|string|alpha',
            'top_rated_product' => 'sometimes|numeric',
            'discount' => 'sometimes|numeric',
            'zipcode' => 'sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {

            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->filled('order') ? $request->input('order') : 'DESC';
            $sort = $request->filled('sort') ? $request->input('sort') : 'p.id';
            $product_id = $request->filled('id') ? $request->input('id') : '';
            $product_ids = $request->input('product_ids', null);
            $type = $request->has('type') ? $request->input('type') : '';
            if (!is_null($product_ids)) {
                $product_id = explode(",", $product_ids);
            }
            $language_code = $request->attributes->get('language_code');
            $store_id = $request->input('store_id') ? (int) $request->input('store_id') : '';
            $seller_id = $request->input('seller_id') ? (int) $request->input('seller_id') : '';
            $user_id = $request->input('user_id', '');
            $category_id = $request->filled('category_id') ? $request->input('category_id') : '';
            $brand_id = $request->filled('brand_id') ? $request->input('brand_id') : '';
            $filters['minimum_price'] = $request->filled('minimum_price') ? $request->input('minimum_price') : '';
            $filters['maximum_price'] = $request->filled('maximum_price') ? $request->input('maximum_price') : '';
            $filters['discount'] = $request->filled('discount') ? $request->input('discount', 0) : 0;
            // $filters['most_popular_products'] = $request->filled('most_popular_products') ? $request->input('most_popular_products') : '';
            $zipcode = $request->filled('zipcode') ? $request->input('zipcode') : 0;
            $filters = [
                'search' => $request->input('search', null),
                'tags' => $request->input('tags', ''),
                'flag' => $request->has('flag') && $request->input('flag') !== '' ? $request->input('flag') : '',
                'attribute_value_ids' => !empty($request->input('attribute_value_ids')) ? explode(',', $request->input('attribute_value_ids', null)) : '',
                'is_similar_products' => $request->input('is_similar_products', null),
                'product_type' => $request->input('top_rated_product') == 1 ? 'top_rated_product_including_all_products' : $request->input('product_type'),
                'show_only_active_products' => $request->input('show_only_active_products', true),
                'show_only_stock_product' => $request->input('show_only_stock_product', false),
                'minimum_price' => $request->input('minimum_price', ''),
                'maximum_price' => $request->input('maximum_price', ''),
                'discount' => $request->input('discount', 0),
                // 'most_popular_products' => $request->input('most_popular_products', ''),
            ];

            if ($request->filled('zipcode')) {
                $is_pincode = Zipcode::where('zipcode', $zipcode)->exists();
                if ($is_pincode) {
                    $zipcode_id = Zipcode::where('zipcode', $zipcode)->firstOrFail()->id;

                    $zipcode = $zipcode_id;
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'Products Not Found !',
                        'language_message_key' => 'products_not_found',
                        'data' => [],
                    ], 200);
                }
            }
            $language_code = $request->attributes->get('language_code');
            // $products = fetchComboProductOld($user_id, $filters, $product_id, $limit, $offset, $sort, $order, '', $zipcode, $seller_id, $store_id, $category_id, $brand_id, $type, '', $language_code);
            $products = app(ComboProductService::class)->fetchComboProduct($user_id, $filters, $product_id, $limit, $offset, $sort, $order, '', $zipcode, $seller_id, $store_id, $category_id, $brand_id, $type, '', $language_code);
            // dd($products);
            $filtered_brand_ids = array_filter($products['brand_ids'], function ($value) {
                return !empty($value);
            });
            $brand_ids = implode(',', $filtered_brand_ids);
            $response = [
                'error' => !$products['combo_product']->isEmpty() ? false : true,
                'message' => !$products['combo_product']->isEmpty() ? 'Products retrived successfully!' : 'No products found',
                'language_message_key' => !$products['combo_product']->isEmpty() ? 'products_retrieved_successfully' : 'no_products_found',
                'total' => (isset($products['total'])) ? strval($products['total']) : 0,
                'min_price' => $products['min_price'],
                'max_price' => $products['max_price'],
                'category_ids' => isset($products['category_ids']) && !empty($products['category_ids']) ? implode(',', $products['category_ids']) : '',
                'brand_ids' => isset($products['brand_ids']) && !empty($products['brand_ids']) ? $brand_ids : '',
                'data' => $products['combo_product'],
            ];
            return response()->json($response);
        }
    }
    public function get_settings(AddressController $addressController, Request $request)
    {
        /*
            type : payment_method // { default : all  } optional
            user_id:  15 { optional }
            limit:25            // { default - 25 } optional
            offset:0            // { default - 0 } optional
        */
        $rules = [
            'type' => 'sometimes|in:payment_method,store_setting',
            'user_id' => 'sometimes|numeric|exists:users,id',
            'store_id' => 'sometimes|numeric|exists:stores,id',
            'limit' => 'sometimes|numeric',
            'offset' => 'sometimes|numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {


            $type = $request->input('type', 'all');
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $user_id = $request->input('user_id', '');
            $store_id = $request->input('store_id', '');
            $tags = $general_settings = array();

            $language_code = $request->attributes->get('language_code');


            if ($type == 'all' || $type == 'payment_method') {

                $filter['tags'] = $request->input('tags', '');

                $products = app(ProductService::class)->fetchProduct(null, $filter, null, null, $limit, $offset, 'products.id', 'DESC', null, '', '', '', $store_id, '', '', '', $language_code);

                for ($i = 0; $i < count($products); $i++) {
                    if (!empty($products['product'][$i]->tags)) {
                        $tags = array_merge($tags, $products['product'][$i]->tags);
                    }
                }
                $settings = [
                    'logo' => 0,
                    'privacy_policy' => 1,
                    'terms_and_conditions' => 1,
                    'fcm_server_key' => 1,
                    'contact_us' => 1,
                    'payment_method' => 1,
                    'about_us' => 1,
                    'currency' => 0,
                    'user_data' => 0,
                    'system_settings' => 1,
                    'shipping_policy' => 1,
                    'return_policy' => 1,
                    'shipping_method' => 1,
                    'pusher_settings' => 1,
                ];
                if ($type == 'payment_method') {

                    if (!$request->bearerToken()) {
                        return response()->json([
                            'error' => true,
                            'message' => 'Please provide a valid token',
                            'code' => 401,
                        ], 401);
                    }

                    $settings_res['payment_method'] = app(SettingService::class)->getSettings($type, $settings[$type]);
                    $settings_res['payment_method'] = json_decode($settings_res['payment_method'], true);
                    if (isset($user_id) && !empty($user_id)) {
                        $cart_total_response = app(CartService::class)->getCartTotal($user_id, false, 0, '', $store_id);

                        $cod_allowed = isset($cart_total_response[0]->is_cod_allowed) ? $cart_total_response[0]->is_cod_allowed : 1;
                        $settings_res['is_cod_allowed'] = $cod_allowed;
                    } else {
                        $settings_res['is_cod_allowed'] = 1;
                    }

                    $general_settings = $settings_res;
                } else {
                    foreach ($settings as $type => $isjson) {
                        if ($type == 'payment_method') {
                            continue;
                        }
                        $general_settings[$type] = [];
                        $settings_res = app(SettingService::class)->getSettings($type, $isjson);
                        $settings_res = json_decode($settings_res, true);

                        if ($type == 'logo') {
                            $logo_setting = app(SettingService::class)->getSettings('system_settings', true);
                            $logo_setting = json_decode($logo_setting, true);
                            $settings_res = app(MediaService::class)->getMediaImageUrl($logo_setting['logo']);
                        }
                        if ($type == 'user_data' && isset($user_id) && !empty($user_id)) {
                            $cart_total_response = app(CartService::class)->getCartTotal($user_id, false, 0, '', $store_id);
                            $res = $addressController->getAddress($user_id, null, false, true);

                            if (!empty($res)) {
                                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $res[0]->pincode], 'id')[0]->id;
                                if (!$zipcode_id->isEmpty()) {
                                    $zipcode = fetchDetails(Zipcode::class, ['id' => $zipcode_id], 'zipcode')[0]->zipcode;
                                }
                            }
                            $settings_res = fetchUsers($user_id);
                            $settings_res = [
                                'cities' => $settings_res->cities ?? '',
                                'street' => $settings_res->street ?? '',
                                'area' => $settings_res->area ?? '',
                                'cart_total_items' => 0, // Initialize to 0, you can update it later
                                'pincode' => isset($zipcode) ? $zipcode : '',
                            ];
                        } elseif ($type == 'user_data' && !isset($user_id)) {
                            $settings_res = '';
                        }
                        //Strip tags in case of terms_and_conditions and privacy_policy

                        if ($isjson && isset($settings_res[$type])) {
                            array_push($general_settings[$type], $settings_res[$type]);
                        } else {
                            array_push($general_settings[$type], $settings_res);
                        }
                    }
                    $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
                    $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
                    $general_settings['currency'] = $currency;
                }

                unset($general_settings['system_settings'][0]['ai_setting']);
                unset($general_settings['shipping_method'][0]['password']);
                unset($general_settings['shipping_method'][0]['email']);
                unset($general_settings['shipping_method'][0]['webhook_token']);
                $general_settings['shipping_method'][0]['minimum_free_delivery_order_amount'] = isset($general_settings['shipping_method'][0]['minimum_free_delivery_order_amount']) && $general_settings['shipping_method'][0]['minimum_free_delivery_order_amount'] !== null ? $general_settings['shipping_method'][0]['minimum_free_delivery_order_amount'] : '';
                $general_settings['terms_and_conditions'][0] = isset($general_settings['terms_and_conditions'][0]) && $general_settings['terms_and_conditions'][0] !== null ? $general_settings['terms_and_conditions'][0] : '';
                // Loop through the array and replace null values with an empty string
                if (isset($general_settings['system_settings']) && !empty($general_settings['system_settings'])) {
                    $base_url = url('/'); // or config('app.url')
                    foreach ($general_settings['system_settings'][0] as $key => $value) {
                        if ($value === null) {
                            $general_settings['system_settings'][0][$key] = "";
                        } elseif (in_array($key, ['logo', 'favicon']) && !empty($value)) {
                            $general_settings['system_settings'][0][$key] = app(MediaService::class)->getImageUrl($value);
                        }
                    }
                }
                if (!isset($general_settings['payment_method']) && !empty($general_settings['payment_method'])) {
                    $general_settings['payment_method'] = array_map(function ($value) {
                        return $value === null ? "" : $value;
                    }, $general_settings['payment_method']);
                }
                $response = [
                    'error' => false,
                    'message' => 'Settings retrieved successfully',
                    'language_message_key' => 'settings_retrieved_successfully',
                    'data' => $general_settings,
                ];
                $response['data']['tags'] = $tags;

                if (isset($general_settings['system_settings'][0]['on_boarding_image']) && !empty($general_settings['system_settings'][0]['on_boarding_image'])) {
                    $onboarding_images = $general_settings['system_settings'][0]['on_boarding_image'];
                    if (isset($onboarding_images) && !empty($onboarding_images)) {
                        foreach ($onboarding_images as &$image) {
                            $image = app(MediaService::class)->getImageUrl($image, "", "", 'image', 'MEDIA_PATH');
                        }
                    }
                } else {
                    $onboarding_images = [];
                }
                $general_settings['system_settings'][0]['on_boarding_image'] = $onboarding_images;


                // Add asset paths to onboarding videos
                $onboarding_videos = [];
                if (isset($general_settings['system_settings'][0]['on_boarding_video']) && !empty($general_settings['system_settings'][0]['on_boarding_video'])) {
                    $onboarding_videos = $general_settings['system_settings'][0]['on_boarding_video'];

                    if (isset($onboarding_videos) && !empty($onboarding_videos)) {
                        foreach ($onboarding_videos as &$video) {
                            $video = app(MediaService::class)->getImageUrl($video, "", "", 'image', 'MEDIA_PATH');
                        }
                    }
                }

                $general_settings['system_settings'][0]['on_boarding_video'] = $onboarding_videos;

                $general_settings['user_data'] = (isset($general_settings['user_data'][0]) && !empty($general_settings['user_data'][0])) ? $general_settings['user_data'][0] : [];
                $response['data'] = $general_settings;
            } else {

                $response = [
                    'error' => true,
                    'message' => 'Settings Not Found',
                    'language_message_key' => 'settings_not_found',
                    'data' => [],
                ];
            }
            return response()->json($response);
        }
    }

    public function get_slider_images(CategoryController $categoryController, Request $request)
    {
        $offset = request()->query('offset', 0);
        $limit = request()->query('limit', 1);
        $rules = [
            'store_id' => 'required|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $store_id = $request->input('store_id') ? (int) $request->input('store_id') : '';
            $res = fetchDetails(Slider::class, ['store_id' => $store_id], '*');
            $language_code = $request->attributes->get('language_code');
            for ($i = 0; $i < count($res); $i++) {
                if ($res[$i]->link == null || empty($res[$i]->link)) {
                    $res[$i]->link = "";
                }

                // Use app(MediaService::class)->getMediaImageUrl function to get the image URL
                $res[$i]->image = app(MediaService::class)->getMediaImageUrl($res[$i]->image);

                if (strtolower($res[$i]->type) == 'categories') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $cat_res = $categoryController->getCategories($id);
                    $res[$i]->data = $cat_res->original['categories'];
                } elseif (strtolower($res[$i]->type) == 'products') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $pro_res = app(ProductService::class)->fetchProduct(NULL, NULL, $id, '', $limit, $offset, '', '', '', '', '', '', $store_id, '', '', '', $language_code);
                    $res[$i]->data = $pro_res['product'];
                } elseif (strtolower($res[$i]->type) == 'combo_products') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $combo_pro_res = app(ComboProductService::class)->fetchComboProduct('', '', $id, '20', '0', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
                    $res[$i]->data = $combo_pro_res['combo_product'];
                } else {
                    $res[$i]->data = [];
                }
            }

            if (!empty($res)) {
                $response = [
                    'error' => false,
                    'message' => 'Sliders Retrieved Successfully',
                    'language_message_key' => 'sliders_retrieved_successfully',
                    'data' => $res,
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => 'No Sliders Found',
                    'language_message_key' => 'no_sliders_found',
                    'data' => $res,
                ];
            }

            return response()->json($response);
        }
    }

    public function update_fcm(Request $request)
    {
        // Validation rules

        $rules = [
            'user_id' => 'sometimes|numeric|exists:users,id',
            'fcm_id' => 'required',
            'is_delete' => 'sometimes|boolean',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        // Check if the user is authenticated
        if (auth()->check()) {
            $user_id = auth()->user()->id;
        }

        // Get fcm_id from request
        $fcm_id = $request->input('fcm_id') ? $request->input('fcm_id') : '';
        $is_delete = $request->input('is_delete'); // New delete parameter

        // If the delete parameter is set to 1, handle deletion
        if ($is_delete == 1) {
            if (isset($user_id) && !empty($user_id) && !empty($fcm_id)) {
                // Delete the entry from user_fcm table
                $deleted = UserFcm::where('user_id', $user_id)
                    ->where('fcm_id', $fcm_id)
                    ->delete();

                if ($deleted) {
                    $response = [
                        'error' => false,
                        'message' => 'FCM ID deleted successfully',
                        'language_message_key' => 'deleted_successfully',
                        'data' => [],
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => 'No entry found to delete!',
                        'language_message_key' => 'no_entry_found',
                        'data' => [],
                    ];
                }
            } else {
                // Handle case where user_id or fcm_id is not set
                $response = [
                    'error' => true,
                    'message' => 'User ID and FCM ID are required for deletion!',
                    'language_message_key' => 'user_id_fcm_id_required',
                    'data' => [],
                ];
            }
        } else {
            // Handle insertion logic
            if (!empty($fcm_id)) {
                if (isset($user_id) && !empty($user_id)) {
                    // Prepare the data for insertion
                    $fcm_data = [
                        'fcm_id' => $fcm_id,
                        'user_id' => $user_id,
                    ];

                    // Check if the FCM ID already exists for the user
                    $existing_fcm = UserFcm::where('user_id', $user_id)
                        ->where('fcm_id', $fcm_id)
                        ->first();

                    if (!$existing_fcm) {
                        // If it doesn't exist, create a new entry
                        $user_res = UserFcm::insert($fcm_data);

                        // Prepare the response
                        if ($user_res) {
                            $response = [
                                'error' => false,
                                'message' => 'FCM ID stored successfully',
                                'language_message_key' => 'stored_successfully',
                                'data' => [],
                            ];
                        } else {
                            $response = [
                                'error' => true,
                                'message' => 'Insertion Failed!',
                                'language_message_key' => 'insertion_failed',
                                'data' => [],
                            ];
                        }
                    } else {
                        // If the FCM ID already exists, prepare a response indicating this
                        $response = [
                            'error' => true,
                            'message' => 'FCM ID already exists for this user.',
                            'language_message_key' => 'fcm_id_exists',
                            'data' => [],
                        ];
                    }
                } else {
                    // Handle case where user_id is not set
                    $response = [
                        'error' => true,
                        'message' => 'User ID is required!',
                        'language_message_key' => 'user_id_required',
                        'data' => [],
                    ];
                }
            }
        }

        return response()->json($response);
    }


    public function reset_password_old(Request $request)
    {
        /* Parameters to be passed
            mobile_no:7894561235
            new: pass@123
        */
        $rules = [
            'mobile_no' => 'required|numeric|digits_between:1,16',
        ];

        $messages = [
            'mobile_no.required' => 'Mobile Number is required.',
            'mobile_no.numeric' => 'Mobile Number must be numeric.',
            'mobile_no.digits_between' => 'Mobile Number must be between 1 and 16 digits.',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages, null, true)) {
            return $response;
        } else {
            $mobile_no = $request->input('mobile_no');
            $new_pass = $request->input('new');
            $identityColumn = config('auth.defaults.passwords') === 'users.email' ? 'email' : 'mobile';

            $user = User::where($identityColumn, $mobile_no)->first();

            if (!$user) {
                $response = [
                    'error' => true,
                    'message' => 'User does not exist!',
                    'language_message_key' => 'user_does_not_exist',
                    'data' => [],
                ];
                return response()->json($response);
            }

            $status = Password::broker()->sendResetLink(
                ['email' => $user->email]
            );

            if ($status === Password::RESET_LINK_SENT) {
                $response = [
                    'error' => false,
                    'message' => 'Password reset link sent successfully!',
                    'language_message_key' => 'password_reset_link_sent_successfully!',
                    'data' => [],
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Unable to send password reset link.',
                    'language_message_key' => 'unable_to_send_password_reset_link',
                    'data' => [],
                ];
            }
            return response()->json($response);
        }
    }
    public function reset_password(Request $request)
    {
        $rules = [
            'mobile_no' => 'required|numeric|digits_between:1,16',
        ];

        $messages = [
            'mobile_no.required' => 'Mobile Number is required.',
            'mobile_no.numeric' => 'Mobile Number must be numeric.',
            'mobile_no.digits_between' => 'Mobile Number must be between 1 and 16 digits.',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages, null, true)) {
            return $response;
        }

        $mobile_no = $request->input('mobile_no');

        // Find user based on mobile number
        $user = User::where('mobile', $mobile_no)->first();

        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User does not exist!',
                'language_message_key' => 'user_does_not_exist',
                'data' => [],
            ]);
        }

        // Send reset link based on user's email
        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'error' => false,
                'message' => 'Password reset link sent successfully!',
                'language_message_key' => 'password_reset_link_sent_successfully!',
                'data' => [],
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Unable to send password reset link.',
                'language_message_key' => 'unable_to_send_password_reset_link',
                'data' => [],
            ]);
        }
    }
    public function get_login_identity()
    {
        $response = [
            'error' => false,
            'message' => 'Data Retrieved Successfully',
            'language_message_key' => 'data_retrieved_successfully',
            'data' => array('identity' => (config('auth.defaults.passwords') === 'users.email' ? 'email' : 'mobile')),
        ];
        return response()->json($response);
    }
    public function verify_user(Request $request)
    {

        $rules = [
            'mobile' => 'numeric',
            'email' => 'sometimes|nullable|email',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }



        $mobile = $request->input('mobile');
        $email = $request->input('email');

        // Check if mobile or email exists in users table
        $user = null;
        if (isset($mobile) && isExist(['mobile' => $mobile], User::class)) {
            $user = User::where('mobile', $mobile)->first();
        } elseif (isset($email) && isExist(['email' => $email], User::class)) {
            $user = User::where('email', $email)->first();
        }

        $authentication_settings = app(SettingService::class)->getSettings('system_settings', true);
        $authentication_settings = json_decode($authentication_settings, true);

        if ($authentication_settings['authentication_method'] == "firebase") {
            if ($user) {
                Auth::login($user);
                $token = $user->createToken('authToken')->plainTextToken;
                $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user->id], 'fcm_id');

                $fcm_ids_array = array_map(function ($item) {
                    return $item->fcm_id;
                }, $fcm_ids->all());
                $user_data = $this->getUserDataArray($user);
                $user_data['fcm_id'] = $fcm_ids_array;

                return response()->json([
                    'error' => false,
                    'message' => 'User Logged in successfully',
                    'language_message_key' => 'user_logged_in_successfully',
                    'token' => $token,
                    'user' => $user_data,
                ]);
            }
        } else {
            if ($user) {

                $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user->id], 'fcm_id');

                $fcm_ids_array = array_map(function ($item) {
                    return $item->fcm_id;
                }, $fcm_ids->all());
                Auth::login($user);
                $token = $user->createToken('authToken')->plainTextToken;

                $user_data = $this->getUserDataArray($user);
                $user_data['fcm_id'] = $fcm_ids_array;
                return response()->json([
                    'error' => false,
                    'message' => 'User Logged in successfully',
                    'language_message_key' => 'user_logged_in_successfully',
                    'token' => $token,
                    'user' => $user_data,
                ]);
            } else {
                $mobile_data = array(
                    'mobile' => $mobile
                );

                if (request()->has('mobile') && !Otps::where('mobile', request('mobile'))->exists()) {
                    Otps::insert($mobile_data);
                }

                $otps = Otps::where('mobile', $mobile)->get()->toArray();

                $otp = random_int(100000, 999999);
                $data = set_user_otp($mobile, $otp);

                // Assume send_otp is a function that sends the OTP to the user's mobile
                set_user_otp($mobile, $otp);

                return response()->json([
                    'error' => false,
                    'message' => 'OTP sent successfully',
                    'language_message_key' => 'otp_sent_successfully',
                ]);
            }
        }

        return response()->json([
            'error' => true,
            'message' => 'User Not Registered',
            'language_message_key' => 'user_not_registered',
            'code' => 102,
            'data' => [],
        ]);
    }

    public function verify_otp(Request $request)
    {
        // Validate the input

        $rules = [
            'mobile' => 'required|numeric',
            'otp' => 'required|numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        $mobile = $request->input('mobile');
        $otp = $request->input('otp');
        $auth_settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);

        if ($auth_settings['authentication_method'] == "sms") {
            $otps = Otps::where('mobile', $mobile)->first();

            if (!$otps) {
                return response()->json([
                    'error' => true,
                    'message' => 'OTP not found for this mobile number',
                    'language_message_key' => 'data_not_found',
                ]);
            }
            $time_expire = checkOTPExpiration($otps->created_at);

            if ($time_expire['error'] == 1) {
                return response()->json([
                    'error' => true,
                    'message' => $time_expire['message'],
                ]);
            }

            if ($otps->otp != $otp) {
                return response()->json([
                    'error' => true,
                    'message' => 'OTP not valid, check again',
                    'language_message_key' => 'invalid_otp_supplied',
                ]);
            } else {
                Otps::where('mobile', $mobile)->update(['varified' => 1]);
            }
        }

        return response()->json([
            'error' => false,
            'message' => 'OTP Verified Successfully',
            'language_message_key' => 'otp_verified_successfully',
            'data' => [],
        ]);
    }

    public function resend_otp(Request $request)
    {
        // Validate the input

        $rules = [
            'mobile' => 'required|numeric',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }



        $mobile = $request->input('mobile');
        $auth_settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);

        if ($auth_settings['authentication_method'] == "sms") {
            $otps = Otps::where('mobile', $mobile)->first();

            if (!$otps) {
                return response()->json([
                    'error' => true,
                    'message' => 'No OTP found for this mobile number',
                    'language_message_key' => 'data_not_found',
                    'data' => [],
                ]);
            }

            $otp = random_int(100000, 999999);
            $data = set_user_otp($mobile, $otp);

            // Optionally, you can send the OTP here using a hypothetical function send_otp
            set_user_otp($mobile, $otp);

            return response()->json([
                'error' => false,
                'message' => 'Ready to send OTP request via SMS!',
                'language_message_key' => 'ready_to_send_otp',
            ]);
        }

        return response()->json([
            'error' => true,
            'message' => 'Invalid authentication method',
            'language_message_key' => 'invalid_authentication_method',
            'data' => [],
        ]);
    }

    private function getUserDataArray($user)
    {
        $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user->id], 'fcm_id');

        $fcm_ids_array = array_map(function ($item) {
            return $item->fcm_id;
        }, $fcm_ids->all());
        return [
            'id' => $user->id ?? '',
            'ip_address' => $user->ip_address ?? '',
            'username' => $user->username ?? '',
            'email' => $user->email ?? '',
            'mobile' => $user->mobile ?? '',
            'image' => app(MediaService::class)->getMediaImageUrl($user->image, 'USER_IMG_PATH'),
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
            'is_notification_on' => $user->is_notification_on ?? '',
        ];
    }
    public function register_user(Request $request)
    {

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'mobile' => 'required|numeric|unique:users,mobile',
            'country_code' => 'required|string|max:255',

            'fcm_id' => 'nullable|string|max:255',
            'referral_code' => 'nullable|string|unique:users,referral_code|max:255',
            'friends_code' => 'nullable|string|max:255',

            'password' => 'string|max:255',
        ];

        $messages = [
            'mobile.unique' => 'The mobile number is already registered. Please log in.',
            'email.unique' => 'The email is already registered. Please log in.',
        ];

        if ($response = $this->HandlesValidation($request, $rules, $messages, null, true)) {
            return $response;
        } else {
            if ($request->filled('friends_code')) {
                $friends_code = $request->input('friends_code');
                $friend = User::where('referral_code', $friends_code)->first();

                if (!$friend) {
                    $response = [
                        'error' => true,
                        'message' => 'Invalid friends code! Please pass the valid referral code of the inviter',
                        'language_message_key' => 'invalid_friends_code_pass_valid_referral_code',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
            $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);
            $wallet_balnace = isset($settings['wallet_balance_amount']) && !empty($settings['wallet_balance_amount']) ? $settings['wallet_balance_amount'] : '';
            $additional_data = [
                'username' => $request->name,
                'mobile' => $request->mobile,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'country_code' => $request->country_code,
                'referral_code' => $request->referral_code,
                'friends_code' => $request->friends_code,
                'type' => 'phone',
                'role_id' => 2,
            ];

            $identity_column = config('auth.defaults.passwords') === 'users.email' ? 'email' : 'mobile';
            $identity = ($identity_column == 'mobile') ? $request->mobile : $request->email;
            $lastInsertId = User::insertGetId($additional_data);

            if ($lastInsertId) {

                // add fcm id in user fcm table

                $fcm_data = [
                    'fcm_id' => $request->fcm_id,
                    'user_id' => $lastInsertId,
                ];
                $existing_fcm = UserFcm::where('user_id', $lastInsertId)
                    ->where('fcm_id', $request->fcm_id)
                    ->first();
                if (!$existing_fcm) {
                    UserFcm::insert($fcm_data);
                }

                // update user's welcome wallet balance

                User::where($identity_column, $identity)->update(['active' => 1]);
                $data = User::select('users.id', 'users.username', 'users.email', 'users.mobile', 'c.name as city_name', 'users.is_notification_on')
                    ->where($identity_column, $identity)
                    ->leftJoin('cities as c', 'c.id', '=', 'users.city')
                    ->groupBy('users.email')
                    ->get()
                    ->toArray();
                if (isset($settings['wallet_balance_status']) && !empty($settings['wallet_balance_status']) && $settings['wallet_balance_status'] == 1) {
                    app(WalletService::class)->updateWalletBalance('credit', $lastInsertId, $wallet_balnace, 'Welcome Wallet Amount Credited for Usre ID  : ' . $lastInsertId);
                }
                foreach ($data as $row) {
                    $row = outputEscaping($row);
                    $tempRow = [
                        'id' => isset($row['id']) && !empty($row['id']) ? $row['id'] : '',
                        'username' => isset($row['username']) && !empty($row['username']) ? $row['username'] : '',
                        'email' => isset($row['email']) && !empty($row['email']) ? $row['email'] : '',
                        'mobile' => isset($row['mobile']) && !empty($row['mobile']) ? $row['mobile'] : '',
                        'city_name' => isset($row['city_name']) && !empty($row['city_name']) ? $row['city_name'] : '',
                        'area_name' => isset($row['area_name']) && !empty($row['area_name']) ? $row['area_name'] : '',
                        'is_notification_on' => isset($row['is_notification_on']) && !empty($row['is_notification_on']) ? intval($row['is_notification_on']) : '',
                    ];

                    $rows[] = $tempRow;
                }

                $response = [
                    'error' => false,
                    'message' => 'Registered Successfully',
                    'language_message_key' => 'registered_successfully',
                    'data' => $rows,
                ];

                return response()->json($response);
            } else {
                $response = [
                    'error' => false,
                    'message' => 'Registration Fail',
                    'language_message_key' => 'registration_fail',
                    'data' => [],
                ];
                return response()->json($response);
            }
        }
    }
    public function update_user(Request $request)
    {
        /*
            username:hiten{optional}
            dob:12/5/1982{optional}
            mobile:7852347890 {optional}
            email:amangoswami@gmail.com	{optional}
            address:Time Square	{optional}
            // area:ravalwadi	{optional}
            city:23	{optional}
            pincode:56	    {optional}
            latitude:45.453	{optional}
            longitude:45.453	{optional}
            //file
            image:[]
            //optional parameters
            referral_code:Userscode
            old:12345
            new:345234
            is_notification_on:1/0
        */
        if (auth()->check()) {
            $user_id = auth()->user()->id;
        }
        $rules = [
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user_id),
            ],
            'dob' => 'nullable|date',
            'city' => 'nullable|numeric',

            'address' => 'nullable|string',
            'pincode' => 'nullable|numeric',
            'username' => 'nullable|string',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
            'referral_code' => 'nullable|string',
        ];

        if (!empty($request->input('old')) || !empty($request->input('new'))) {
            $rules = [
                'old' => 'required',
                'new' => 'required|min:6',
            ];
        }
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {


            $user_details = fetchDetails(User::class, ['id' => $user_id], '*');
            if (!empty($request->input('old')) || !empty($request->input('new'))) {
                $identity = config('auth.defaults.passwords') === 'users.email' ? 'email' : 'mobile';

                if (!empty($user_details)) {

                    $user = $request->user();

                    if (!$user) {
                        return response()->json([
                            'error' => true,
                            'message' => 'User is not authenticated',
                            'language_message_key' => 'user_not_authenticated'
                        ], 401);
                    }

                    // Attempt to change the password
                    if (!Hash::check($request->input('old'), $user->password)) {
                        // If the old password does not match
                        return response()->json([
                            'error' => true,
                            'message' => 'Old password is incorrect',
                            'language_message_key' => 'old_password_incorrect'
                        ], 400);
                    }
                    $user->password = bcrypt($request->input('new'));
                    $user->save();

                    $file_path = str_replace('\\', '/', public_path(config('constants.USER_IMG_PATH') . $user_details[0]->image));

                    if (empty($user_details[0]->image) || File::exists($file_path) == FALSE) {

                        $user_details[0]->image = str_replace('\\', '/', public_path(config('constants.NO_USER_IMAGE')));
                    } else {

                        $user_details[0]->image = $file_path;
                    }
                    $user_details[0]->image_sm = app(MediaService::class)->getImageUrl($user_details[0]->image, 'thumb', 'sm', 'image');
                    $response = [
                        'error' => false,
                        'message' => 'Password Update Successfully',
                        'language_message_key' => 'password_update_successful',
                        'data' => $user_details,
                    ];
                    return response()->json($response);
                } else {
                    $response = [
                        'error' => true,
                        'message' => 'User not exists',
                        'language_message_key' => 'user_not_exists',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
            $is_updated = false;
            /* update referral_code if it is empty in user's database */
            $referral_code = $request->input('referral_code');
            if (isset($referral_code) && !empty($referral_code)) {

                if (empty($user_details[0]->referral_code)) {

                    updateDetails(['referral_code' => $referral_code], ['id' => $user_id], User::class);
                    $is_updated = true;
                }
            }

            // Create the directory if it doesn't exist
            $userImgPath = public_path(config('constants.USER_IMG_PATH'));

            if (!File::exists($userImgPath)) {
                File::makeDirectory($userImgPath, 0755, true);
            }
            $rules = [
                'image' => 'image|mimes:jpeg,gif,jpg,png',
            ];
            if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
                return $response;
            }
            //----------------- image upload code ----------------------------
            if ($request->hasFile('image')) {
                $image = $request->file('image');


                $imageNewName = $image->getClientOriginalName();
                $image_path = $userImgPath . '/' . $imageNewName;


                if ($image->move($userImgPath, $imageNewName)) {
                    $file_name = $image->getClientOriginalName();
                    $file_extension = $image->getClientOriginalExtension();
                    if (File::exists($userImgPath)) {
                        $file_size = filesize($userImgPath);
                    }
                    $file_mime = $image->getClientMimeType();
                    $type = 'document';
                    if (str_contains($file_mime, 'image')) {
                        $type = 'image';
                        if (File::exists($image)) {
                            $imageSize = getimagesize($image);
                            if ($imageSize === false) {
                                list($width, $height) = [0, 0];
                            } else {
                                list($width, $height) = $imageSize;
                            }
                        } else {
                            // Handle the case where the file does not exist
                            list($width, $height) = [0, 0];
                        }
                    } elseif (str_contains($file_mime, 'video')) {
                        $type = 'video';
                        $width = null;
                        $height = null;
                    }

                    $response = [
                        'error' => false,
                        'message' => 'Image uploaded successfully',
                        'language_message_key' => 'image_uploaded_successfully',
                        'data' => [],
                    ];
                } else {
                    $response = [
                        'error' => true,
                        'message' => 'Error uploading image',
                        'language_message_key' => 'error_uploading_image',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
            $username = $request->input('username', '');
            $email = $request->input('email');
            $dob = $request->input('dob');
            $mobile = $request->input('mobile');
            $address = $request->input('address');
            $city = $request->input('city');
            $area = $request->input('area');
            $pincode = $request->input('pincode');
            $latitude = $request->input('latitude');
            $longitude = $request->input('longitude');
            $is_notification_on = $request->input('is_notification_on');
            $set = [];
            if (isset($username) && !empty($username)) {
                $set['username'] = $username;
            }
            if (isset($email) && !empty($email)) {
                $set['email'] = $email;
            }
            if (isset($dob) && !empty($dob)) {
                $set['dob'] = $dob;
            }
            if (isset($mobile) && !empty($mobile)) {
                $set['mobile'] = $mobile;
            }
            if (isset($address) && !empty($address)) {
                $set['address'] = $address;
            }
            if (isset($city) && !empty($city)) {
                $set['city'] = $city;
            }
            if (isset($area) && !empty($area)) {
                $set['area'] = $area;
            }
            if (isset($pincode) && !empty($pincode)) {
                $set['pincode'] = $pincode;
            }
            if (isset($latitude) && !empty($latitude)) {
                $set['latitude'] = $latitude;
            }
            if (isset($longitude) && !empty($longitude)) {
                $set['longitude'] = $longitude;
            }
            $set['is_notification_on'] = $is_notification_on;

            if ($request->hasFile('image')) {
                $set['image'] = '/' . $imageNewName;
            }
            if (!empty($set)) {
                updateDetails($set, ['id' => $user_id], User::class);
                $user_details = fetchDetails(User::class, ['id' => $user_id], '*');

                foreach ($user_details as $row) {
                    $row = outputEscaping($row);
                    $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $row->id], 'fcm_id');

                    $fcm_ids_array = array_map(function ($item) {
                        return $item->fcm_id;
                    }, $fcm_ids->all());

                    $defaultImage = app(MediaService::class)->getImageUrl('no-user-img.jpeg', "", "", "image", 'NO_USER_IMAGE');
                    $imageUrl = "";
                    if ($row->image !== "") {

                        $imageUrl = app(MediaService::class)->getImageUrl($row->image, 'thumb', 'sm', 'image', 'USER_IMG_PATH');
                    }
                    $image = $imageUrl ? $imageUrl : $defaultImage;
                    $tempRow = [
                        'id' => intval($row->id ?? ''),
                        'ip_address' => $row->ip_address ?? '',
                        'username' => $row->username ?? '',
                        'password' => $row->password ?? '',
                        'email' => $row->email ?? '',
                        'mobile' => $row->mobile ?? '',
                        'image' => $image,
                        'balance' => intval($row->balance ?? '0'),
                        'activation_selector' => $row->activation_selector ?? '',
                        'activation_code' => $row->activation_code ?? '',
                        'forgotten_password_selector' => $row->forgotten_password_selector ?? '',
                        'forgotten_password_code' => $row->forgotten_password_code ?? '',
                        'forgotten_password_time' => $row->forgotten_password_time ?? '',
                        'remember_selector' => $row->remember_selector ?? '',
                        'remember_code' => $row->remember_code ?? '',
                        'created_on' => intval($row->created_on) ?? '',
                        'last_login' => intval($row->last_login) ?? '',
                        'active' => intval($row->active ?? ''),
                        'is_notification_on' => $row->is_notification_on ?? '',
                        'company' => $row->company ?? '',
                        'address' => $row->address ?? '',
                        'bonus' => $row->bonus ?? '',
                        'cash_received' => intval($row->cash_received ?? '0.00'),
                        'dob' => $row->dob ?? '',
                        'country_code' => intval($row->country_code ?? ''),
                        'city' => $row->city ?? '',
                        'area' => $row->area ?? '',
                        'street' => $row->street ?? '',
                        'pincode' => $row->pincode ?? '',
                        'serviceable_zones' => $row->serviceable_zones ?? '',
                        'apikey' => $row->apikey ?? '',
                        'referral_code' => $row->referral_code ?? '',
                        'friends_code' => $row->friends_code ?? '',
                        'fcm_id' => array_values($fcm_ids_array) ?? '',
                        'latitude' => $row->latitude ?? '',
                        'longitude' => $row->longitude ?? '',
                        'created_at' => $row->created_at ?? '',
                        'type' => $row->type ?? '',
                    ];
                    $rows[] = $tempRow;
                }
                $response = [
                    'error' => false,
                    'message' => 'Profile Update Successfully',
                    'language_message_key' => 'profile_updated_successfully',
                    'data' => $rows,
                ];
                return response()->json($response);
            } else if ($is_updated == true) {
                $user_details = fetchDetails(User::class, ['id' => $user_id], '*');
                foreach ($user_details as $row) {
                    $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $row->id], 'fcm_id');

                    $fcm_ids_array = array_map(function ($item) {
                        return $item->fcm_id;
                    }, $fcm_ids->all());
                    $row = outputEscaping($row);
                    $defaultImage = app(MediaService::class)->getImageUrl('no-user-img.jpeg', "", "", "image", 'NO_USER_IMAGE');
                    $imageUrl = "";
                    if ($row->image !== "") {

                        $imageUrl = app(MediaService::class)->getImageUrl($row->image, 'thumb', 'sm', 'image', 'USER_IMG_PATH');
                    }

                    $image = $imageUrl ? $imageUrl : $defaultImage;

                    $tempRow = [
                        'id' => intval($row->id ?? ''),
                        'ip_address' => $row->ip_address ?? '',
                        'username' => $row->username ?? '',
                        'password' => $row->password ?? '',
                        'email' => $row->email ?? '',
                        'mobile' => $row->mobile ?? '',
                        'image' => $image,
                        'balance' => intval($row->balance ?? '0'),
                        'activation_selector' => $row->activation_selector ?? '',
                        'activation_code' => $row->activation_code ?? '',
                        'forgotten_password_selector' => $row->forgotten_password_selector ?? '',
                        'forgotten_password_code' => $row->forgotten_password_code ?? '',
                        'forgotten_password_time' => $row->forgotten_password_time ?? '',
                        'remember_selector' => $row->remember_selector ?? '',
                        'remember_code' => $row->remember_code ?? '',
                        'created_on' => $row->created_on ?? '',
                        'last_login' => $row->last_login ?? '',
                        'active' => intval($row->active ?? ''),
                        'is_notification_on' => $row->is_notification_on ?? '',
                        'company' => $row->company ?? '',
                        'address' => $row->address ?? '',
                        'bonus' => $row->bonus ?? '',
                        'cash_received' => intval($row->cash_received ?? '0.00'),
                        'dob' => $row->dob ?? '',
                        'country_code' => intval($row->country_code ?? ''),
                        'city' => $row->city ?? '',
                        'area' => $row->area ?? '',
                        'street' => $row->street ?? '',
                        'pincode' => $row->pincode ?? '',
                        'serviceable_zones' => $row->serviceable_zones ?? '',
                        'apikey' => $row->apikey ?? '',
                        'referral_code' => $row->referral_code ?? '',
                        'friends_code' => $row->friends_code ?? '',
                        'fcm_id' => array_values($fcm_ids_array) ?? '',
                        'latitude' => $row->latitude ?? '',
                        'longitude' => $row->longitude ?? '',
                        'created_at' => $row->created_at ?? '',
                    ];
                    $rows[] = $tempRow;
                }
                $response = [
                    'error' => false,
                    'message' => 'Profile Update Successfully',
                    'language_message_key' => 'profile_updated_successfully',
                    'data' => $rows,
                ];
                return response()->json($response);
            }
        }
    }
    public function delete_user(Request $request)
    {
        /*
            mobile:9874563214
            password:12345695
        */

        $rules = [
            'mobile' => 'nullable|numeric',
            'user_id' => 'numeric|exists:users,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {

            if (auth()->check()) {

                $user_id = auth()->user()->id;
            }

            $mobile = $request->input('mobile');
            $password = $request->input('password');
            $user_data = fetchDetails(User::class, ['id' => $user_id, 'mobile' => $mobile], ['id', 'username', 'password', 'active', 'mobile']);

            if ($user_data) {
                $credentials = [
                    'mobile' => $request->input('mobile'),
                    'password' => $request->input('password'),
                ];

                if (Auth::guard('api')->attempt($credentials)) {
                    $user = Auth::user();

                    if ($user) {
                        $role_id = $user->role_id;
                        $user_roles = fetchDetails(Role::class, ['id' => $role_id]);

                        if ($user_roles[0]->id == 2) {
                            $status = 'awaiting,received,processed,shipped';
                            $multiple_status = explode(',', $status);
                            $orders = app(OrderService::class)->fetchOrders('', $request->input('user_id'), $multiple_status);

                            foreach ($orders['order_data'] as $order) {

                                updateDetails(['status' => 'cancelled'], ['id' => $order->id], Order::class);
                                updateDetails(['active_status' => 'cancelled'], ['id' => $order->id], Order::class);

                                updateDetails(['active_status' => 'cancelled'], ['order_id' => $order->id], OrderItems::class);

                                app(OrderService::class)->process_refund($order->id, 'cancelled', 'orders');

                                $data = fetchDetails(OrderItems::class, ['order_id' => $order->id], ['product_variant_id', 'quantity']);
                                $product_variant_ids = [];
                                $qtns = [];

                                foreach ($data as $d) {
                                    $product_variant_ids[] = $d->product_variant_id;
                                    $qtns[] = $d->quantity;
                                }
                                app(ProductService::class)->updateStock($product_variant_ids, $qtns, 'plus');
                            }
                            deleteDetails(['id' => $user_id], User::class);
                            return response()->json(['error' => false, 'message' => 'User Deleted Successfully', 'language_message_key' => 'user_deleted_successfully']);
                        } else {
                            return response()->json(['error' => true, 'message' => 'Details do not match', 'language_message_key' => 'details_do_not_match']);
                        }
                    } else {
                        $response = [
                            'error' => true,
                            'message' => 'Details Does not Match',
                            'language_message_key' => 'details_do_not_match',
                            'data' => [],
                        ];
                        return response()->json($response);
                    }
                } else {
                    $response = [
                        'error' => true,
                        'message' => 'User Not Found',
                        'language_message_key' => 'user_does_not_exist',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
        }
    }
    public function add_to_favorites(Request $request)
    {
        /*
            product_id:60
            product_type:regular // {regular / combo}
            is_seller:1          // optional
            seller_id:18         // optional if is_seller is 0
        */

        $rules = [
            'product_id' => 'required_if:is_seller,0|numeric',
            'product_type' => 'required_if:is_seller,0|in:regular,combo',
            'is_seller' => 'required|in:0,1',
            'seller_id' => 'required_if:is_seller,1|numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        if (auth()->check()) {
            $user_id = auth()->user()->id;
        } else {
            $response = [
                'error' => true,
                'message' => 'Please Login first.',
                'language_message_key' => 'please_login_first',
                'code' => 102,
            ];
            return response()->json($response);
        }

        $product_id = $request->input('product_id');
        $product_type = $request->input('product_type');
        $is_seller = $request->input('is_seller', 0);
        $seller_id = $request->input('seller_id', null);
        if ($is_seller == 0) {
            if (isExist(['user_id' => $user_id, 'product_id' => $product_id], Favorite::class)) {
                $response = [
                    'error' => true,
                    'message' => 'Already added to favorite !',
                    'language_message_key' => 'already_added_to_favorite',
                    'data' => [],
                ];
                return response()->json($response);
            }
        } elseif ($is_seller == 1) {
            if (isExist(['user_id' => $user_id, 'seller_id' => $seller_id], Favorite::class)) {
                $response = [
                    'error' => true,
                    'message' => 'Already added to favorite !',
                    'language_message_key' => 'already_added_to_favorite',
                    'data' => [],
                ];
                return response()->json($response);
            }
        }

        $data = [
            'user_id' => $user_id,
            'product_id' => $product_id,
            'product_type' => $product_type,
            'is_seller' => $is_seller,
            'seller_id' => $seller_id,
        ];

        $fav_res = Favorite::create($data);
        if ($fav_res) {
            $response = [
                'error' => false,
                'message' => 'Added to favorite !',
                'language_message_key' => 'added_to_favorite',
            ];
        } else {
            $response = [
                'error' => true,
                'message' => 'Not Added to favorite !',
                'language_message_key' => 'not_added_to_favorite',
            ];
        }

        return response()->json($response);
    }


    public function remove_from_favorites(Request $request)
    {
        /*
            product_id:60
            product_type:regular // {regular / combo}
            is_seller:1          // optional
            seller_id:18         // optional if is_seller is 0
        */


        $rules = [
            'product_id' => 'required_if:is_seller,0|numeric',
            'product_type' => 'required_if:is_seller,0|in:regular,combo',
            'is_seller' => 'required|in:0,1',
            'seller_id' => 'required_if:is_seller,1|numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        if (!auth()->check()) {
            return response()->json([
                'error' => true,
                'message' => 'Please Login first.',
                'language_message_key' => 'please_login_first',
                'code' => 102,
            ]);
        }

        $user_id = auth()->user()->id;
        $is_seller = $request->input('is_seller');
        $product_id = $request->input('product_id');
        $product_type = $request->input('product_type');
        $seller_id = $request->input('seller_id');

        if ($is_seller == 0) {
            if (!isExist(['user_id' => $user_id, 'product_id' => $product_id, 'product_type' => $product_type], Favorite::class)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Item not added as favorite !',
                    'language_message_key' => 'item_not_added_as_favorite',
                    'data' => [],
                ]);
            }

            $data = [
                'user_id' => $user_id,
                'product_id' => $product_id,
                'product_type' => $product_type,
            ];
        } else {
            if (!isExist(['user_id' => $user_id, 'seller_id' => $seller_id], Favorite::class)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Seller not added as favorite !',
                    'language_message_key' => 'seller_not_added_as_favorite',
                    'data' => [],
                ]);
            }

            $data = [
                'user_id' => $user_id,
                'seller_id' => $seller_id,
            ];
        }

        deleteDetails($data, Favorite::class);

        return response()->json([
            'error' => false,
            'message' => 'Removed from favorite',
            'language_message_key' => 'removed_from_favorite',
            'data' => [],
        ]);
    }

    public function get_favorites(Request $request)
    {
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'product_limit' => 'numeric',
            'product_offset' => 'numeric',
            'seller_limit' => 'numeric',
            'seller_offset' => 'numeric',
        ];

        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        if (!auth()->check()) {
            return response()->json([
                'error' => true,
                'message' => 'Please Login first.',
                'language_message_key' => 'please_login_first',
                'code' => 102,
            ]);
        }

        $user_id = auth()->id();
        $store_id = $request->input('store_id');
        $product_limit = $request->input('product_limit', 25);
        $product_offset = $request->input('product_offset', 0);
        $seller_limit = $request->input('seller_limit', 25);
        $seller_offset = $request->input('seller_offset', 0);
        $language_code = $request->attributes->get('language_code');

        // ✅ Favorite Products (regular)
        $favoriteProducts = Favorite::with(['product.store'])
            ->where('user_id', $user_id)
            ->where('product_type', 'regular')
            ->whereHas('product', function ($q) use ($store_id) {
                $q->where('store_id', $store_id)->where('status', 1);
            })
            ->skip($product_offset)
            ->take($product_limit)
            ->get();

        // ✅ Favorite Combo Products
        $favoriteComboProducts = Favorite::with(['comboProduct.store'])
            ->where('user_id', $user_id)
            ->where('product_type', 'combo')
            ->whereHas('comboProduct', function ($q) use ($store_id) {
                $q->where('store_id', $store_id)->where('status', 1);
            })
            ->skip($product_offset)
            ->take($product_limit)
            ->get();

        $result_products = [];

        foreach ($favoriteProducts as $fav) {
            $details = app(ProductService::class)->fetchProduct($user_id, null, $fav->product_id, '', $product_limit, $product_offset, '', '', '', '', '', '', $store_id, '', '', '', $language_code);
            if (!empty($details)) {
                $result_products[] = $details['product'][0] ?? null;
            }
        }

        foreach ($favoriteComboProducts as $fav) {
            $details = app(ComboProductService::class)->fetchComboProduct($user_id, null, $fav->product_id, '20', '0', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
            if (!empty($details)) {
                $result_products[] = $details['combo_product'][0] ?? null;
            }
        }

        $total_products = Favorite::where('user_id', $user_id)
            ->where('product_type', 'regular')
            ->whereHas('product', fn($q) => $q->where('store_id', $store_id)->where('status', 1))
            ->count();

        $total_combo_products = Favorite::where('user_id', $user_id)
            ->where('product_type', 'combo')
            ->whereHas('comboProduct', fn($q) => $q->where('store_id', $store_id)->where('status', 1))
            ->count();

        // ✅ Favorite Sellers
        $favoriteSellers = Favorite::with([
            'seller.stores' => function ($q) use ($store_id) {
                $q->where('store_id', $store_id);
            },
            'seller.user'
        ])
            ->where('user_id', $user_id)
            ->whereNotNull('seller_id')
            ->get()
            ->filter(fn($fav) => $fav->seller && $fav->seller->stores->isNotEmpty());

        $result_sellers = [];

        $paginatedSellers = $favoriteSellers->slice($seller_offset)->take($seller_limit);

        foreach ($paginatedSellers as $fav) {
            $store = $fav->seller->stores->first();
            $user = $fav->seller->user;

            $seller_total_products = Product::where('store_id', $store_id)->where('seller_id', $fav->seller->id)->count();

            $result_sellers[] = [
                'seller_id' => $fav->seller->id,
                'user_id' => $user_id,
                'store_name' => $store->pivot->store_name ?? '',
                'store_description' => $store->pivot->store_description ?? '',
                'rating' => $store->pivot->rating ?? 0,
                'no_of_ratings' => $store->pivot->no_of_ratings ?? 0,
                'store_logo' => app(MediaService::class)->getMediaImageUrl($store->pivot->logo ?? '', 'SELLER_IMG_PATH'),
                'total_products' => $seller_total_products,
                'is_favorite' => 1,
                'seller_address' => trim(str_replace(["\n", "\r"], '', $user->address ?? '')),
            ];
        }

        $response = [
            'error' => false,
            'message' => 'Data Retrieved Successfully',
            'language_message_key' => 'data_retrieved_successfully',
            'products' => [
                'total' => $total_products + $total_combo_products,
                'data' => array_values(array_filter($result_products)),
            ],
            'sellers' => [
                'total' => $favoriteSellers->count(),
                'data' => array_values($result_sellers),
            ],
        ];

        if (empty($result_products) && empty($result_sellers)) {
            $response['error'] = true;
            $response['message'] = 'No Favorite Product(s) or Seller(s) Are Added';
            $response['language_message_key'] = 'no_favorite_products_or_sellers_added';
        }

        return response()->json($response);
    }


    public function add_address(AddressController $addressController, Request $request)
    {
        /*
        type:Home/Office/Others
        country_code:+91
        mobile:1234567890
        name:test user
        alternate_mobile:9876543210
        address:Time Square Empire
        landmark:Bhuj-Mirzapar Highway
        area_id:1
        city_id:2
        city_name:bhuj
        area_name:jay nagar
        general_area_name:jay nagar
        pincode_name:370001
        pincode:0123456
        state:Gujarat
        country:India
        latitude:45.453
        longitude:45.453
        is_default:1
        */


        $rules = [
            'mobile' => 'numeric',
            'alternate_mobile' => 'numeric',
            'pincode_name' => 'numeric',
            'pincode' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $request['user_id'] = $user_id;

            $addressController->store($request);

            $res = $addressController->getAddress($user_id, null, true);

            $response = [
                'error' => false,
                'message' => 'Address Added Successfully',
                'language_message_key' => 'address_added_successfully',
                'data' => $res,
            ];
            return response()->json($response);
        }
    }
    public function update_address(AddressController $addressController, Request $request)
    {
        /*
        id:2
        type:Home/Office/Others
        country_code:+91
        mobile:1234567890
        name:test user
        alternate_mobile:9876543210
        address:Time Square Empire
        landmark:Bhuj-Mirzapar Highway
        area_id:1
        city_id:2
        city_name:bhuj
        area_name:jay nagar
        general_area_name:jay nagar
        pincode_name:370001
        pincode:0123456
        state:Gujarat
        country:India
        latitude:45.453
        longitude:45.453
        is_default:1
        */
        $rules = [
            'id' => 'numeric|required|exists:addresses,id',
            'mobile' => 'numeric',
            'alternate_mobile' => 'numeric',
            'pincode_name' => 'numeric',
            'pincode' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $request['user_id'] = $user_id;
            $addressController->store($request);

            $res = $addressController->getAddress(null, $request->input('id'), true);
            $response = [
                'error' => false,
                'message' => 'Address updated Successfully',
                'language_message_key' => 'address_updated_successfully',
                'data' => $res,
            ];
            return response()->json($response);
        }
    }

    public function delete_address(AddressController $addressController, Request $request)
    {
        $rules = [
            'id' => 'numeric|required|exists:addresses,id',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $id = $request->input('id');
            $addressController->destroy($id);
            $response = [
                'error' => false,
                'message' => 'Address Deleted Successfully',
                'language_message_key' => 'address_deleted_successfully',
                'data' => [],
            ];
            return response()->json($response);
        }
    }
    public function get_address(AddressController $addressController, Request $request)
    {
        $rules = [
            'mobile' => 'numeric',
            'alternate_mobile' => 'numeric',
            'pincode_name' => 'numeric',
            'pincode' => 'numeric',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }

            $res = $addressController->getAddress($user_id);


            if (!$res->isEmpty()) {

                $is_default_counter = collect($res)->pluck('is_default')->countBy();


                if (!isset($is_default_counter['1']) && !empty($res)) {
                    updateDetails(['is_default' => '1'], ['id' => $res[0]->id], Address::class);
                    $res = $addressController->getAddress($user_id);
                }
                $response = [
                    'error' => false,
                    'message' => 'Address Retrieved Successfully',
                    'language_message_key' => 'address_retrieved_successfully',
                    'data' => $res,
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => 'No Address Found !',
                    'language_message_key' => 'no_address_found',
                    'data' => [],
                ];
            }
            return response()->json($response);
        }
    }

    public function get_user_cart(Request $request, CartController $cartController, PromoCodeController $promoCodeController)
    {
        /*
          delivery_pincode:370001 //optional when standard shipping is on
          only_delivery_charge:0 (default:0)// if 1 it's only returen shiprocket delivery charge OR return all cart information
          address_id:2 // only when only_delivery_charge is 1
          is_saved_for_later: 1 { default:0 }
        */

        $rules = [
            'only_delivery_charge' => 'required|numeric',
            'address_id' => $request->input('only_delivery_charge') == 1 ? 'required|numeric' : '',
            'delivery_pincode' => $request->input('only_delivery_charge') != 1 ? 'numeric' : '',
            'is_saved_for_later' => 'numeric',
            'store_id' => 'required|numeric|exists:stores,id',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            // dd($user_id);
            $settings = [];
            $settings = app(SettingService::class)->getSettings('shipping_method', true);
            $settings = json_decode($settings, true);
            $language_code = $request->attributes->get('language_code');
            $only_delivery_charge = request('only_delivery_charge', 0);
            $store_id = request('store_id') != null ? request('store_id') : '';
            $is_saved_for_later = request('is_saved_for_later', 0);
            $address_id = request('address_id', 0);
            $deliveryPincode = request('delivery_pincode', '');
            $area_id = fetchDetails(Address::class, ['id' => $address_id], ['area_id', 'area', 'pincode', 'city']);
            $zipcode = !$area_id->isEmpty() ? $area_id[0]->pincode : '';
            $city = !$area_id->isEmpty() ? $area_id[0]->city : '';
            if (isset($zipcode) && !empty($zipcode)) {
                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
                $zipcode_id = !$zipcode_id->isEmpty() ? $zipcode_id[0]->id : '';
            }
            if (isset($city) && !empty($city)) {
                $city_id = fetchDetails(City::class, ['name->en' => $city], 'id');
                $city_id = !$city_id->isEmpty() ? $city_id[0]->id : '';
            }

            $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
            $product_availability = "";
            $product_deliverability_type = fetchDetails(Store::class, ['id' => $store_id], 'product_deliverability_type');
            $product_deliverability_type = !$product_deliverability_type->isEmpty() ? $product_deliverability_type[0]->product_deliverability_type : '';
            if (!empty($address_id)) {
                if ($product_deliverability_type == 'city_wise_deliverability') {
                    $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id, $is_saved_for_later);
                } else {
                    $product_availability = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $zipcode, $zipcode_id, $store_id, '', '', $is_saved_for_later);
                }
            } else {
                $product_availability = [];
            }
            if (
                $only_delivery_charge == 1 &&
                !empty($product_availability) &&
                isset($product_availability[0]['is_valid_wight']) &&
                $product_availability[0]['is_valid_wight'] == 0
            ) {
                $response = [
                    'error' => true,
                    'message' => $product_availability[0]['message'] ?? 'Invalid weight',
                    'data' => [],
                ];
                return response()->json($response);
            }

            $product_availability = is_array($product_availability) ? $product_availability : [];
            // dd($product_availability);
            $productDeliverableCollection = new Collection($product_availability);


            $productNotDeliverable = $productDeliverableCollection->filter(function ($var) {
                return $var['is_deliverable'] === false && $var['product_id'] !== null;
            })->values();
            $cart_user_data = $cartController->get_user_cart($user_id, $is_saved_for_later, '', $store_id);
            $cart_total = 0.0;

            for ($i = 0; $i < count($cart_user_data); $i++) {

                $cart_total += $cart_user_data[$i]->sub_total;
                if (!isset($product_availability[$i])) {
                    continue;
                }
                $cart[$i]['delivery_by'] = $product_availability[$i]['delivery_by'];
                $cart[$i]['is_deliverable'] = $product_availability[$i]['is_deliverable'];
                $cart[$i]['product_id'] = $product_availability[$i]['product_id'];
                $cart[$i]['product_qty'] = $product_availability[$i]['product_qty'];
                $cart[$i]['minimum_free_delivery_order_qty'] = $product_availability[$i]['minimum_free_delivery_order_qty'];
                $cart[$i]['product_delivery_charge'] = $product_availability[$i]['product_delivery_charge'];
                $cart[$i]['product_type'] = $cart_user_data[$i]->product_type;
                $cart[$i]['type'] = $cart_user_data[$i]->type;

                if ($cart[$i]['delivery_by'] == "standard_shipping") {
                    $standard_shipping_cart[] = $cart[$i];
                } else {
                    $local_shipping_cart[] = $cart[$i];
                }
            }
            $cart_total_response = app(CartService::class)->getCartTotal($user_id, false, $is_saved_for_later, $address_id, $store_id);
            if ($only_delivery_charge == 1) {
                $address_detail = fetchDetails(Address::class, ['id' => $address_id], 'pincode');
                $delivery_pincode = !$address_detail->isEmpty() ? $address_detail[0]->pincode : "";
            } else {
                $delivery_pincode = (isset($deliveryPincode)) ? $deliveryPincode : 0;
            }

            $tmp_cart_user_data = $cart_user_data;
            $weight = 0;

            if (!empty($tmp_cart_user_data)) {
                for ($i = 0; $i < count($tmp_cart_user_data); $i++) {

                    $cart_user_data[$i]->product_delivery_charge = '';

                    if ($tmp_cart_user_data[$i]->cart_product_type == 'regular') {
                        $product_data = Product_variants::select('product_id', 'availability')
                            ->where('id', $tmp_cart_user_data[$i]->product_variant_id)
                            ->first();
                    }
                    if ($tmp_cart_user_data[$i]->cart_product_type == 'combo') {
                        $product_data = ComboProduct::select('id as product_id', 'availability')
                            ->where('id', $tmp_cart_user_data[$i]->id)
                            ->first();
                    }

                    if (!empty($product_data->product_id)) {
                        // dd($product_data->product_id);
                        if ($tmp_cart_user_data[$i]->cart_product_type == 'regular') {
                            $pro_details = app(ProductService::class)->fetchProduct(NULL, NULL, $product_data->product_id, '', '20', '0', '', '', '', '', '', '', $store_id, '', '', '', $language_code);
                        } else {
                            $pro_details = app(ComboProductService::class)->fetchComboProduct(NULL, NULL, $product_data->product_id, '20', '0', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
                        }

                        if (!empty($pro_details['product']) || !empty($pro_details['combo_product'])) {
                            if ($tmp_cart_user_data[$i]->cart_product_type == 'regular') {

                                $pro_details['product'][0]['net_amount'] = $cart_user_data[$i]->net_amount;

                                if ($pro_details['product'][0]['availability'] == 0 && $pro_details['product'][0]['availability'] != null) {
                                    updateDetails(['is_saved_for_later' => '1'], $cart_user_data[$i]->id, Cart::class);
                                    unset($cart_user_data[$i]);
                                }

                                if (!empty($pro_details['product'])) {
                                    $cart_user_data[$i]->product_details = $pro_details['product'];
                                } else {
                                    deleteDetails(['id' => $cart_user_data[$i]->id], Cart::class);
                                    unset($cart_user_data[$i]);
                                    continue;
                                }
                            }

                            if ($tmp_cart_user_data[$i]->cart_product_type == 'combo') {

                                $pro_details['combo_product'][0]->net_amount = $cart_user_data[$i]->net_amount;

                                if ($pro_details['combo_product'][0]->availability == 0 && $pro_details['combo_product'][0]->availability != null) {
                                    updateDetails(['is_saved_for_later' => '1'], $cart_user_data[$i]->id, Cart::class);
                                    unset($cart_user_data[$i]);
                                }

                                if (!empty($pro_details['combo_product'])) {
                                    $cart_user_data[$i]->product_details = $pro_details['combo_product'];
                                } else {
                                    deleteDetails(['id' => $cart_user_data[$i]->id], Cart::class);
                                    unset($cart_user_data[$i]);
                                    continue;
                                }
                            }
                        } else {
                            deleteDetails(['id' => $cart_user_data[$i]->id], Cart::class);
                            unset($cart_user_data[$i]);
                            continue;
                        }
                    } else {
                        deleteDetails(['id' => $cart_user_data[$i]->id], Cart::class);
                        unset($cart_user_data[$i]);
                        continue;
                    }
                }

                if (isset($settings['shiprocket_shipping_method']) && $settings['shiprocket_shipping_method'] == 1) {

                    $parcels = app(ShiprocketService::class)->makeShippingParcels($tmp_cart_user_data);
                    $parcels_details = app(ShiprocketService::class)->checkParcelsDeliverability($parcels, $delivery_pincode);
                }
            }

            if ($cart_user_data->isEmpty()) {
                $response = [
                    'error' => true,
                    'message' => 'Cart Is Empty !',
                    'language_message_key' => 'cart_is_empty',
                    'data' => array(),
                ];
                return response()->json($response);
            }
            if ($only_delivery_charge == 0) {
                $search = request()->input('search', '');
                $limit = request()->input('limit', 25);
                $offset = request()->input('offset', 0);
                $order = request()->input('order', 'DESC');
                $sort = request()->input('sort', 'id');

                $product_variant_ids = [];
                $qtys = [];
                $product_types = [];

                foreach ($tmp_cart_user_data as $item) {
                    $product_variant_ids[] = $item->product_variant_id;
                    $qtys[] = $item->qty;
                    $product_types[] = $item->product_type;
                }
                // dd($product_variant_ids);
                $check_current_stock_status = validateStock($product_variant_ids, $qtys, $product_types);
                // dd($check_current_stock_status);
                $out_of_stock_data = [];

                if (isset($check_current_stock_status['error']) && $check_current_stock_status['error'] == true) {
                    foreach ($check_current_stock_status['errors'] as $error_item) {
                        $variant_id = (int) $error_item['product_variant_id'];

                        $out_of_stock_product_data = collect($tmp_cart_user_data)->firstWhere('product_variant_id', $variant_id);
                        if (!empty($out_of_stock_product_data)) {
                            $out_of_stock_data[] = $out_of_stock_product_data->toArray();
                        }
                    }
                }
                $response = [
                    'error' => false,
                    'message' => 'Data Retrieved From Cart !',
                    'language_message_key' => 'data_retrieved_from_cart',
                    'total_quantity' => $cart_total_response['quantity'],
                    'sub_total' => $cart_total_response['sub_total'],
                    'item_total' => $cart_total_response['item_total'],
                    'discount' => $cart_total_response['discount'] ?? strval($cart_total_response['discount']),
                    'currency_sub_total_data' => app(CurrencyService::class)->getPriceCurrency($cart_total_response['sub_total']),
                ];
                // dd($local_shipping_cart);
                $deliveryCharge = 0;
                if (!empty($local_shipping_cart)) {

                    $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);

                    $deliveryCharge = app(DeliveryService::class)->getDeliveryCharge(request()->input('address_id'), $cart_total_response['sub_total'], $local_shipping_cart, $store_id);
                    // dd($deliveryCharge);
                    if ((isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'zipcode_wise_delivery_charge') || (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'city_wise_delivery_charge') || (isset($settings[0]->delivery_charge_type) && !empty($settings[0]->delivery_charge_type) && $settings[0]->delivery_charge_type == 'global_delivery_charge')) {
                        $response['delivery_charge'] = str_replace(",", "", $deliveryCharge);
                        $response['currency_delivery_charge_data'] = app(CurrencyService::class)->getPriceCurrency($response['delivery_charge']);
                    } else {
                        $response['delivery_charge'] = 0;
                        for ($i = 0; $i < count($tmp_cart_user_data); $i++) {
                            $cart_user_data[$i]->product_delivery_charge = isset($deliveryCharge[$i]['delivery_charge']) && !empty($deliveryCharge[$i]['delivery_charge']) ? $deliveryCharge[$i]['delivery_charge'] : 0;
                            $cart_user_data[$i]->currency_product_delivery_charge_data = app(CurrencyService::class)->getPriceCurrency($cart_user_data[$i]->product_delivery_charge);
                            $response['delivery_charge'] += (float) $cart_user_data[$i]->product_delivery_charge;
                            $response['currency_delivery_charge_data'] = app(CurrencyService::class)->getPriceCurrency($response['delivery_charge']);
                        }
                    }
                }
                $response['delivery_charge'] = isset($response['delivery_charge']) ? strval($response['delivery_charge']) : '0';
                $deliveryCharge = (is_array($deliveryCharge) && isset($deliveryCharge[0]['delivery_charge']))
                    ? (float) $deliveryCharge[0]['delivery_charge']
                    : (float) $deliveryCharge;
                $response['tax_percentage'] = $cart_total_response['tax_percentage'] ?? "0";
                $response['tax_amount'] = $cart_total_response['tax_amount'] ?? "0";
                $response['currency_tax_amount_data'] = app(CurrencyService::class)->getPriceCurrency($response['tax_amount']);

                $response['overall_amount'] = $cart_total_response['overall_amount'];
                $response['currency_overall_amount_data'] = app(CurrencyService::class)->getPriceCurrency($response['overall_amount']);
                $response['total_arr'] = $cart_total_response['total_arr'];
                $response['currency_total_arr_data'] = app(CurrencyService::class)->getPriceCurrency($response['total_arr']);
                $response['variant_id'] = $cart_total_response['variant_id'];

                if (isset($settings['shiprocket_shipping_method']) && $settings['shiprocket_shipping_method'] == 1) {
                    $response['parcels_details'] = $parcels_details;
                }

                $response['cart'] = array_values($cart_user_data->toArray());
                $response['out_of_stock_data'] = !empty($out_of_stock_data) ? $out_of_stock_data : [];
                $result = $promoCodeController->getPromoCodes($limit, $offset, $sort, $order, $search, $store_id);

                $response['promo_codes'] = $result['data'];

                return response()->json($response);
            } else {
                // if only_delivery_charge is 1
                $data = [];

                if (!empty($standard_shipping_cart)) {

                    $delivery_pincode = fetchDetails(Address::class, ['id' => request()->input('address_id')], 'pincode');
                    $parcels = app(ShiprocketService::class)->makeShippingParcels($tmp_cart_user_data);
                    $parcels_details = app(ShiprocketService::class)->checkParcelsDeliverability($parcels, $delivery_pincode[0]->pincode);

                    if ($settings['shiprocket_shipping_method'] == 1 && $settings['standard_shipping_free_delivery'] == 1 && $cart_total > $settings['minimum_free_delivery_order_amount']) {
                        $data['delivery_charge_with_cod'] = 0;
                        $data['delivery_charge_without_cod'] = 0;
                        $data['estimated_delivery_days'] = $parcels_details['estimated_delivery_days'];
                        $data['estimate_date'] = $parcels_details['estimate_date'];
                    } else {
                        $data['delivery_charge_with_cod'] = $parcels_details['delivery_charge_with_cod'];
                        $data['currency_delivery_charge_with_cod_data'] = app(CurrencyService::class)->getPriceCurrency($data['delivery_charge_with_cod']);
                        $data['delivery_charge_without_cod'] = $parcels_details['delivery_charge_without_cod'];
                        $data['currency_delivery_charge_without_cod_data'] = app(CurrencyService::class)->getPriceCurrency($data['delivery_charge_without_cod']);
                        $data['estimated_delivery_days'] = $parcels_details['estimated_delivery_days'];
                        $data['estimate_date'] = $parcels_details['estimate_date'];
                    }
                }
                $response['error'] = false;
                $response['message'] = 'Data Retrieved Successfully !';
                $response['language_message_key'] = 'data_retrieved_successfully';
                $response['data'] = $data;

                return response()->json($response);
            }
        }
    }

    public function get_sections(Request $request)
    {
        /*
            store_id : 1
            limit:10            // { default - 25 } {optional}
            offset:0            // { default - 0 } {optional}
            user_id:12              {optional}
            section_id:4            {optional}
            attribute_value_ids : 34,23,12 //
            top_rated_product: 1 // { default - 0 } optional
            p_limit:10          // { default - 10 } {optional}
            p_offset:10         // { default - 0 } {optional}
            p_sort:pv.price      // { default - pid } {optional}
            p_order:asc         // { default - desc } {optional}
            discount: 5 // { default - 5 } optional
            min_price:10000          // optional
            max_price:50000          // optional
            zipcode:1          // optional
        */

        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
            'limit' => 'numeric',
            'offset' => 'numeric',
            'section_id' => 'numeric',
            'p_limit' => 'numeric',
            'p_offset' => 'numeric',
            'p_sort' => 'numeric',
            'p_order' => 'string',
            'discount' => 'numeric',
            'zipcode' => 'nullable|string',
            'min_price' => 'nullable|numeric|lte:max_price',
            'max_price' => 'nullable|numeric|gte:min_price',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $limit = $request->filled('limit') ? $request->input('limit', 25) : 25;
            $offset = $request->filled('offset') ? $request->input('offset', 0) : 0;
            $user_id = $request->filled('user_id') ? $request->input('user_id') : 0;
            $section_id = $request->filled('section_id') ? $request->input('section_id') : 0;
            $store_id = $request->filled('store_id') ? $request->input('store_id') : 0;
            $filters['attribute_value_ids'] = $request->input('attribute_value_ids', null);
            $filters['product_type'] = $request->input('top_rated_product') == 1 ? 'top_rated_product_including_all_products' : null;
            $p_limit = $request->filled('p_limit') ? $request->input('p_limit', 10) : 10;
            $p_offset = $request->filled('p_offset') ? $request->input('p_offset', 0) : 0;
            $p_order = $request->filled('p_order') ? $request->input('p_order', 'DESC') : 'DESC';
            $p_sort = $request->filled('p_sort') ? $request->input('p_sort', 'p.id') : 'products.id';
            $filters['discount'] = $request->input('discount', 0);
            $filters['min_price'] = $request->filled('min_price') ? $request->input('min_price', 0) : 0;
            $filters['max_price'] = $request->filled('max_price') ? $request->input('max_price', 0) : 0;
            $zipcode = $request->filled('zipcode') ? $request->input('zipcode', 0) : 0;

            if ($request->filled('zipcode')) {
                $zipcode = $request->input('zipcode');
                $isPincode = Zipcode::where('zipcode', $zipcode)->exists();

                if ($isPincode) {
                    $zipcode_id = Zipcode::where('zipcode', $zipcode)->value('id');
                    $zipcode = $zipcode_id;
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'Products Not Found!',
                        'language_message_key' => 'products_not_found',
                        'data' => [],
                    ], 200);
                }
            }
            $sections = Section::where('store_id', $store_id)
                ->when($request->filled('section_id'), function ($query) use ($request) {
                    return $query->where('id', $request->input('section_id'));
                })
                ->orderBy('row_order')->skip($offset)->take($limit)->get();
            $language_code = $request->attributes->get('language_code');
            if (!$sections->isEmpty()) {
                foreach ($sections as &$section) {
                    $section->title = app(TranslationService::class)->getDynamicTranslation(Section::class, 'title', $section->id, $language_code);
                    $section->short_description = app(TranslationService::class)->getDynamicTranslation(Section::class, 'short_description', $section->id, $language_code);
                    // dd($section->updated_at);
                    if ($section->product_type == 'custom_combo_products') {
                        $section->categories = $section->categories ?: '';
                        $comboproductIds = explode(',', $section->product_ids);
                        $comboproductIds = array_filter($comboproductIds);
                        $products = app(ComboProductService::class)->fetchComboProduct($user_id, '', $comboproductIds, $limit, $offset, $p_sort, $p_order, '', '', '', '', '', '', '', '', $language_code);
                        $response = [
                            'error' => false,
                            'message' => 'Sections retrieved successfully.',
                            'language_message_key' => 'sections_retrieved_successfully',
                        ];
                        $response['min_price'] = isset($products['min_price']) && !empty($products['min_price']) ? strval($products['min_price']) : '0';
                        $response['max_price'] = isset($products['max_price']) && !empty($products['max_price']) ? strval($products['max_price']) : '0';
                        $section->title = app(TranslationService::class)->getDynamicTranslation(Section::class, 'title', $section->id, $language_code);
                        $section->short_description = app(TranslationService::class)->getDynamicTranslation(Section::class, 'short_description', $section->id, $language_code);
                        $section->banner_image = app(MediaService::class)->getMediaImageUrl($section->banner_image);
                        $section->total = strval($products['total']);
                        $section->filters = $products['filters'] ?? [];
                        $section->product_details = $products['combo_product'];

                        $section->product_ids = $section->product_ids ?: '';
                        $category_ids = implode(',', array_filter(collect($products['category_ids'])->unique()->values()->all()));
                        $brand_ids = implode(',', array_filter(collect($products['brand_ids'])->unique()->values()->all()));
                        $section->category_ids = $category_ids;
                        $section->brand_ids = $brand_ids;
                    } else {
                        $productIds = explode(',', $section->product_ids);
                        $productIds = array_filter($productIds);

                        $filters = [
                            'show_only_active_products' => 1,
                            'product_type' => $request->input('top_rated_product') ? 'top_rated_product_including_all_products' : null,
                        ];

                        if (empty($filters['product_type']) && !empty($section->product_type)) {
                            $filters['product_type'] = $section->product_type;
                        }

                        $categories = $section->categories ? explode(',', $section->categories) : '';
                        $products = app(ProductService::class)->fetchProduct($user_id, $filters, $productIds, $categories, $p_limit, $p_offset, $p_sort, $p_order, null, $zipcode, null, '', '', '', '', '', $language_code);
                        if (!empty($products['product'])) {
                            $response = [
                                'error' => false,
                                'message' => 'Sections retrieved successfully.',
                                'language_message_key' => 'sections_retrieved_successfully',
                            ];
                            $response['min_price'] = isset($products['min_price']) && !empty($products['min_price']) ? strval($products['min_price']) : '0';
                            $response['max_price'] = isset($products['max_price']) && !empty($products['max_price']) ? strval($products['max_price']) : '0';
                            $section->title = app(TranslationService::class)->getDynamicTranslation(Section::class, 'title', $section->id, $language_code);
                            $section->short_description = app(TranslationService::class)->getDynamicTranslation(Section::class, 'short_description', $section->id, $language_code);
                            $section->banner_image = app(MediaService::class)->getMediaImageUrl($section->banner_image);
                            $section->total = strval($products['total']);
                            $section->filters = $products['filters'] ?? [];

                            $section->product_details = $products['product'];

                            $section->categories = $section->categories ?: '';



                            $product_id = fetchDetails(Product::class, fields: 'id', where_in_key: 'category_id', where_in_value: explode(',', $section->categories));
                            $product_ids = [];
                            foreach ($product_id as $ids) {

                                $product_ids[] = $ids->id;
                            }


                            // Unset 'total' property from all elements of 'product_details' array
                            foreach ($section->product_details as $product_detail) {
                                unset($product_detail->total);
                            }
                            $category_ids = implode(',', array_filter(collect($section->product_details)->pluck('category_id')->unique()->values()->all()));
                            $brand_ids = implode(',', array_filter(collect($section->product_details)->pluck('brand_id')->unique()->values()->all()));
                            $section->category_ids = $category_ids;
                            $section->product_ids = $section->product_ids ? $section->product_ids : ($section->category_ids ? implode(',', $product_ids) : '');
                            $section->brand_ids = $brand_ids;
                        } else {
                            $response = [
                                'error' => false,
                                'message' => 'Sections retrieved successfully.',
                                'language_message_key' => 'sections_retrieved_successfully',
                            ];
                            $section->total = '0';
                            $section->filters = [];
                        }
                    }
                }
                foreach ($sections as &$section) {
                    foreach ($section as $key => &$value) {
                        $value = $value ?? "";
                        $section->banner_image = app(MediaService::class)->getMediaImageUrl($section->banner_image);
                        $section->created_at = $section->created_at ? Carbon::parse($section->created_at)->format('Y-m-d H:i:s') : '';
                        $section->updated_at = $section->updated_at ? Carbon::parse($section->updated_at)->format('Y-m-d H:i:s') : '';
                    }
                }
                // $response['data'] = $sections;
                $response['data'] = $sections->map(function ($section) {
                    $sectionArray = $section->toArray();
                    $sectionArray['created_at'] = Carbon::parse($section->created_at)->format('Y-m-d H:i:s');
                    $sectionArray['updated_at'] = Carbon::parse($section->updated_at)->format('Y-m-d H:i:s');
                    $sectionArray['banner_image'] = app(MediaService::class)->getMediaImageUrl($section->banner_image);
                    return $sectionArray;
                });
            } else {
                $response = [
                    'error' => true,
                    'message' => 'No sections are available.',
                    'language_message_key' => 'no_sections_available',
                    'data' => [],
                ];
            }

            return response()->json($response);
        }
    }

    public function get_zipcode_by_city_id(AreaController $areaController, Request $request)
    {
        /*
            id:'57'
            limit:25            // { default - 25 } optional
            offset:0            // { default - 0 } optional
            sort:               // { a.name / a.id } optional
            order:DESC/ASC      // { default - ASC } optional
            search:value        // {optional}
        */

        $rules = [
            'city_id' => 'numeric|required|exists:cities,id',
            'limit' => 'numeric',
            'offset' => 'numeric',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $limit = request('limit', 25);
            $offset = request('offset', 0);
            $sort = request('sort', 'zipcode');
            $order = request('order', 'ASC');
            $search = request('search', '');
            $city_id = request('city_id');

            $result = $areaController->getAreaByCity($city_id, $sort, $order, $search, $limit, $offset);
            return response()->json($result);
        }
    }

    public function validate_promo_code(Request $request)
    {
        /*
            promo_code:'NEWOFF10'
            user_id:28
            final_total:'300'
        */
        $rules = [
            'promo_code' => 'required',
            'final_total' => 'required|numeric',

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $promo_code = request('promo_code');
            $final_total = request('final_total');
            $language_code = $request->attributes->get('language_code');
            // dd($language_code);
            $res = app(abstract: PromoCodeService::class)->validatePromoCode($promo_code, $user_id, $final_total, '0', $language_code);
            return response()->json($res->original);
        }
    }

    public function place_order(Request $request, TransactionController $transactionController)
    {
        /*
            store_id:1
            email:testmail123@gmail.com // only enter when ordered product is digital product and one of them is not downloadable(download_allowed = 0)
            delivery_charge:20.0
            latitude:40.1451
            longitude:-45.4545
            promo_code_id:1 {optional}
            payment_method: Paypal / Payumoney / COD / PAYTM
            address_id:17
            delivery_date:10/12/2012
            delivery_time:Today - Evening (4:00pm to 7:00pm)
            is_wallet_used:1 {By default 0}
            wallet_balance_used:1
            order_note:text      //{optional}
            order_payment_currency_code:INR

        */

        $rules = [
            'promo_code_id' => 'nullable',
            'order_note' => 'nullable',
            'is_wallet_used' => 'required|numeric',
            'latitude' => 'numeric',
            'longitude' => 'numeric',
            'delivery_date' => 'nullable',
            'delivery_time' => 'nullable',
            'store_id' => 'required|numeric|exists:stores,id',
            'order_payment_currency_code' => 'required',
            'status' => 'required'

        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {

            if (auth()->check()) {
                $user_id = auth()->user()->id;
                // dd($user_id);
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }

            $store_id = request('store_id') != null ? request('store_id') : '';
            $cart_data = fetchDetails(Cart::class, ['user_id' => $user_id, 'store_id' => $store_id, 'is_saved_for_later' => 0], ['product_variant_id', 'qty', 'product_type']);
            $product_variant_ids = collect($cart_data)->pluck('product_variant_id')->toArray();
            $cart_product_type = collect($cart_data)->pluck('product_type')->toArray();
            $qty = collect($cart_data)->pluck('qty')->toArray();
            $request['product_variant_id'] = implode(',', $product_variant_ids);
            $request['cart_product_type'] = implode(',', $cart_product_type);
            $request['quantity'] = implode(',', $qty);
            $request['mobile'] = auth()->user()->mobile;
            $language_code = $request->attributes->get('language_code');

            // get details based on cart product type
            $productVariant = Product_variants::with('product')
                ->whereIn('id', $product_variant_ids)
                ->whereHas('cart', fn($q) => $q->where('product_type', 'regular'))
                ->orderByRaw('FIELD(id, ' . implode(',', $product_variant_ids) . ')')
                ->get()
                ->map(function ($variant) {
                    return (object) [
                        'type' => $variant->product->type,
                        'download_allowed' => $variant->product->download_allowed,
                        'is_attachment_required' => $variant->product->is_attachment_required,
                        'product_name' => $variant->product->name,
                        'id' => $variant->id,
                    ];
                });

            $comboProductVariant = ComboProduct::whereIn('id', $product_variant_ids)
                ->whereHas('cart', fn($q) => $q->where('product_type', 'combo'))
                ->orderByRaw('FIELD(id, ' . implode(',', $product_variant_ids) . ')')
                ->get()
                ->map(function ($combo) {
                    return (object) [
                        'type' => $combo->product_type,
                        'download_allowed' => $combo->download_allowed,
                        'is_attachment_required' => $combo->is_attachment_required,
                        'product_name' => $combo->title,
                        'id' => $combo->id,
                    ];
                });

            $productVariant = $productVariant->concat($comboProductVariant);

            foreach ($productVariant as $variant) {

                if ($variant->is_attachment_required && empty($request->file('order_attachment'))) {
                    $response = [
                        'error' => true,
                        'message' => 'Order attachment is required for product ' . app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $variant->id, $language_code) . ' and no files were provided.',
                        'code' => 102,
                    ];
                    return response()->json($response);
                }
            }
            $request['attachment_path'] = array();
            if (!File::exists('storage/order_attachments')) {
                File::makeDirectory('storage/order_attachments', 0755, true);
            }
            if ($request->file('order_attachment')) {
                foreach ($product_variant_ids as $variant_id) {
                    foreach ($request->file('order_attachment') as $key => $attachment) {

                        if ($variant_id == $key) {

                            try {
                                $order_attachments['attachment_path'] = '';
                                $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
                                $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->id : 1;
                                $disk = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
                                // dd($disk);
                                $media = StorageType::find($mediaStorageType);
                                $mediaIds = [];
                                if ($request->hasFile('order_attachment')) {
                                    $mediaItem = $media->addMedia($attachment)
                                        ->sanitizingFileName(function ($fileName) use ($media) {
                                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));
                                            $uniqueId = time() . '_' . mt_rand(1000, 9999);
                                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);
                                            return "{$baseName}-{$uniqueId}.{$extension}";
                                        })
                                        ->toMediaCollection('order_attachments', $disk);

                                    $mediaIds[] = $mediaItem->id;
                                    if ($disk == 'public') {
                                        $order_attachments = [
                                            'attachment_path' => 'order_attachments/' . $mediaItem->file_name,
                                        ];
                                    }
                                }
                                if ($disk == 's3') {
                                    $media_list = $media->getMedia('order_attachments');
                                    $media_url = $media_list[($media_list->count()) - (count($mediaIds))]->getUrl();
                                    $order_attachments = [
                                        'attachment_path' => $media_url,
                                    ];
                                    Media::destroy($mediaIds);
                                }

                                $request_data = $request->all();

                                // dd($request_data);
                                $request_data['attachment_path'][$key] = $order_attachments['attachment_path'];
                                $request->merge($request_data);
                            } catch (Exception $e) {

                                return response()->json([
                                    'error' => true,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            }


            $productType = $productVariant->pluck('type')->unique()->toArray();
            $downloadAllowed = $productVariant->pluck('download_allowed')->unique()->toArray();

            if (in_array(0, $downloadAllowed) && $productType[0] === 'digital_product') {

                $rules = [
                    'email' => 'required|email',

                ];
                if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
                    return $response;
                }
            }

            if ($request->input('is_wallet_used') == '1') {
                $rules = [
                    'wallet_balance_used' => 'required|numeric',
                ];
                if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
                    return $response;
                }
            }

            //physical_product product type is used in combo product
            if (in_array($productType[0], ["variable_product", "simple_product", "physical_product"])) {
                $rules = [
                    'address_id' => 'required|numeric',
                ];
                if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
                    return $response;
                }
            }

            $request['order_note'] = $request->filled('order_note') ? $request->input('order_note') : null;

            /* Checking for product availability */

            $area_details = fetchDetails(Address::class, ['id' => $request->input('address_id')], ['pincode', 'city']);

            $zipcode = isset($area_details) && !empty($area_details) ? $area_details[0]->pincode : '';

            $city = isset($area_details) && !empty($area_details) ? $area_details[0]->city : '';

            $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
            // dd($zipcode_id);
            $zipcode_id = !$zipcode_id->isEmpty() ? $zipcode_id[0]->id : '';

            // $city_id = fetchDetails(City::class, ['name' => $city], 'id');
            $city_id = fetchDetails(City::class, ['name->en' => $city], 'id');
            $city_id = isset($city_id) && !empty($city_id) ? $city_id[0]->id : '';


            $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
            if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
                if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                    $productDeliverable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id);
                } else {

                    $productDeliverable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $zipcode, $zipcode_id, $store_id);
                }
            }



            if (!empty($productDeliverable) && ($productType[0] == "variable_product" || $productType[0] == "simple_product" || $productType[0] == "physical_product")) {

                $productDeliverableCollection = new Collection($productDeliverable);
                if (!$productDeliverableCollection->isEmpty()) {
                    // Filter out items where 'is_deliverable' is false and 'product_id' is not null
                    $productNotDeliverable = $productDeliverableCollection->filter(function ($var) {
                        return $var['is_deliverable'] === false && $var['product_id'] !== null;
                    })->values();

                    // Filter out items where 'product_id' is not null
                    $productDeliverable = $productDeliverableCollection->filter(function ($var) {
                        return $var['product_id'] !== null;
                    })->values();
                }

                if (!$productNotDeliverable->isEmpty()) {
                    $response = [
                        'error' => true,
                        'message' => "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.",
                        'language_message_key' => 'some_items_not_deliverable_on_selected_address_change_the_address',
                        'data' => $productDeliverable,
                    ];
                    return response()->json($response);
                } else {
                    $request['is_delivery_charge_returnable'] = isset($request['delivery_charge']) && !empty($request['delivery_charge']) && $request['delivery_charge'] != '' && $request['delivery_charge'] > 0 ? 1 : 0;
                    $request['user_id'] = $user_id;

                    $res = app(OrderService::class)->placeOrder($request);

                    if (!empty($res)) {
                        if ($request['payment_method'] == "bank_transfer" || $request['payment_method'] == "direct_bank_transfer") {

                            $data = new Request([
                                'status' => "awaiting",
                                'txn_id' => null,
                                'message' => null,
                                'order_id' => $res->original['order_id'],
                                'user_id' => $user_id,
                                'type' => $request['payment_method'],
                                'amount' => $res->original['final_total'],
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

                $request['is_delivery_charge_returnable'] = isset($request['delivery_charge']) && !empty($request['delivery_charge']) && $request['delivery_charge'] != '' && $request['delivery_charge'] > 0 ? 1 : 0;
                $request['user_id'] = $user_id;
                $request['store_id'] = $store_id;
                $request['status'] = isset($request['status']) && !empty($request['status']) && $request['status'] != '' ? $request['status'] : 'awaiting';

                $res = app(OrderService::class)->placeOrder($request, '', $language_code);

                if (!empty($res)) {

                    if ($request['payment_method'] == "bank_transfer" || $request['payment_method'] == "direct_bank_transfer") {
                        $data = new Request([
                            'status' => "awaiting",
                            'txn_id' => null,
                            'message' => null,
                            'order_id' => $res->original['order_id'],
                            'user_id' => $user_id,
                            'type' => $request['payment_method'],
                            'amount' => $res->original['final_total'],
                        ]);

                        $transactionController->store($data);
                    }
                }
                return response()->json($res->original);
            }
        }
    }
    public function remove_from_cart(Request $request)
    {
        /*
            product_variant_id:23
            address_id : 2 // optional
            store_id : 1,
            product_type:regular // {regular / combo}
        */
        $rules = [
            'product_variant_id' => 'required|numeric|exists:product_variants,id',
            'address_id' => 'numeric',
            'store_id' => 'required|numeric|exists:stores,id',
            'product_type' => 'required',
            'is_saved_for_later' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $product_variant_id = request('product_variant_id');
            $address_id = request('address_id', '');
            $store_id = request('store_id') != null ? request('store_id') : '';
            $is_saved_for_later = request('is_saved_for_later') != null ? request('is_saved_for_later') : '';
            $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";
            $data = [
                'user_id' => $user_id,
                'product_variant_id' => $product_variant_id,
                'product_type' => $product_type,
                'store_id' => $store_id,
                'is_saved_for_later' => $is_saved_for_later,
            ];
            app(CartService::class)->removeFromCart($data);

            $cart_total_response = app(CartService::class)->getCartTotal($user_id, false, '', $address_id, $store_id);
            $response['error'] = false;
            $response['message'] = 'Removed From Cart !';
            $response['language_message_key'] = 'removed_from_cart';
            if (!$cart_total_response->isEmpty() && isset($cart_total_response)) {
                $response['data'] = [
                    'total_quantity' => strval($cart_total_response['quantity']),
                    'item_total' => $cart_total_response['item_total'] ?? strval($cart_total_response['item_total']),
                    'sub_total' => $cart_total_response['sub_total'] ?? strval($cart_total_response['sub_total']),
                    'discount' => $cart_total_response['discount'] ?? strval($cart_total_response['discount']),
                    'delivery_charge' => isset($cart_total_response['delivery_charge']) && !empty($cart_total_response['delivery_charge']) ? $cart_total_response['delivery_charge'] : '',
                    'currency_delivery_charge_data' => app(CurrencyService::class)->getPriceCurrency($cart_total_response['delivery_charge']),
                    'tax_percentage' => $cart_total_response['tax_percentage'] ?? strval($cart_total_response['tax_percentage']),
                    'tax_amount' => $cart_total_response['tax_amount'] ?? strval($cart_total_response['tax_amount']),
                    'overall_amount' => $cart_total_response['overall_amount'] ?? strval($cart_total_response['overall_amount']),
                    'currency_sub_total_data' => app(CurrencyService::class)->getPriceCurrency($cart_total_response['sub_total']),
                    'total_items' => (isset($cart_total_response[0]->total_items)) ? strval($cart_total_response[0]->total_items) : "0",
                    'max_items_cart' => $settings['maximum_item_allowed_in_cart']
                ];
            } else {
                $response['data'] = [];
            }

            return response()->json($response);
        }
    }

    public function manage_cart(Request $request, CartController $cartController)
    {
        /*
            Add/Update
            store_id:1
            product_variant_id:23
            is_saved_for_later: 1 { default:0 }
            qty:2 // pass 0 to remove qty
            address_id : 2 // optional
            product_type:regular // {regular / combo}
        */
        $rules = [
            'product_variant_id' => 'required|numeric',
            'address_id' => 'nullable',
            'qty' => 'required|numeric',
            'is_saved_for_later' => 'numeric',
            'store_id' => 'required|numeric|exists:stores,id',
            'product_type' => 'required|in:regular,combo',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }


            $product_variant_id = request('product_variant_id') != null ? request('product_variant_id') : "";
            $qty = request('qty') != null ? request('qty') : "";
            $address_id = request('address_id') != null ? request('address_id') : "";
            $store_id = request('store_id') != null ? request('store_id') : "";
            $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";
            $saved_for_later = request('is_saved_for_later') != null ? request('is_saved_for_later') : "";
            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $language_code = $request->attributes->get('language_code');
            $weight = 0;

            if ($product_type == 'regular') {
                if (!isExist(['id' => $product_variant_id], Product_variants::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product Varient not available.',
                        'language_message_key' => 'product_variant_not_available',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            } else {
                if (!isExist(['id' => $product_variant_id], ComboProduct::class)) {
                    $response = [
                        'error' => true,
                        'message' => 'Product not available.',
                        'language_message_key' => 'product_not_available',
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
                        'language_message_key' => 'unable_to_remove_existing_seller_items_try_again_later',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }
            $store_details = fetchDetails(Store::class, ['id' => $store_id], '*');
            $is_single_seller_order_system = !$store_details->isEmpty() ? $store_details[0]->is_single_seller_order_system : "";


            if ($settings['single_seller_order_system'] == 1 || $is_single_seller_order_system == 1) {
                if (!app(CartService::class)->isSingleSeller($product_variant_id, $user_id, $product_type, $store_id)) {
                    $response = [
                        'error' => true,
                        'message' => 'Only single seller items are allow in cart.You can remove privious item(s) and add this item.',
                        'language_message_key' => 'single_seller_item_only_allowed_in_cart',
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
                    'language_message_key' => 'only_digital_or_physical_product_allowed_in_cart',
                    'data' => [],
                ];
                return response()->json($response);
            }

            $local_user_cart = [];

            $settings = app(SettingService::class)->getSettings('system_settings', true);
            $settings = json_decode($settings, true);
            $check_status = ($qty == 0 || $saved_for_later == 1) ? false : true;
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
            $request["user_id"] = $user_id;
            // dd($request->toArray());
            $add_to_cart = app(CartService::class)->addToCart($request->toArray(), $check_status, true);
            // dd($add_to_cart);
            // dd($add_to_cart['message']);
            if (isset($add_to_cart['error']) && $add_to_cart['error'] == true) {
                $response['error'] = true;
                $response['message'] = $add_to_cart['message'];
                $response['language_message_key'] = 'product_is_out_of_stock';
                return response()->json($response);
            }
            // $res = app(CartService::class)->getCartTotal($user_id, false, $saved_for_later, $address_id, $store_id);
            // dd($res);
            if (app(CartService::class)->addToCart($request->toArray(), $check_status, true)) {

                $res = app(CartService::class)->getCartTotal($user_id, false, $saved_for_later, $address_id, $store_id);
                // dd($res);
                $cart_user_data = $cartController->get_user_cart($user_id, $saved_for_later, '', $store_id, $language_code);
                $product_type = collect($cart_user_data)->pluck('type')->unique()->values()->all();

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

                        if ($cartItem->cart_product_type == 'regular') {
                            $productData = Product_variants::select('product_id', 'availability')
                                ->where('id', $cartItem->product_variant_id)
                                ->first();
                        }
                        if ($cartItem->cart_product_type == 'combo') {
                            $productData = ComboProduct::select('id as product_id', 'availability')
                                ->where('id', $cartItem->id)
                                ->first();
                        }
                        if (!empty($productData) && !empty($productData->product_id)) {

                            if ($cartItem->cart_product_type == 'regular') {
                                $proDetails = app(ProductService::class)->fetchProduct(request()->input('user_id'), null, $productData->product_id, '', 20, 0, '', '', '', '', '', '', '', '', '', '', $language_code);
                            } else {
                                $proDetails = app(ComboProductService::class)->fetchComboProduct(request()->input('user_id'), null, $productData->product_id, '20', '', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
                            }

                            if (!empty($proDetails['product']) || !empty($proDetails['combo_product'])) {
                                if ($cartItem->cart_product_type == 'regular') {
                                    if (trim($proDetails['product'][0]['availability']) == 0 && !is_null($proDetails['product'][0]['availability'])) {
                                        updateDetails(['is_saved_for_later' => '1'], ['id' => $cart_user_data[$index]->id], Cart::class);
                                        unset($cart_user_data[$index]);
                                        continue;
                                    }

                                    if (!empty($proDetails['product'])) {
                                        $cart_user_data[$index]->product_details = $proDetails['product'];
                                    } else {
                                        deleteDetails(['id' => $cart_user_data[$index]->id], Cart::class);
                                        unset($cart_user_data[$index]);
                                        continue;
                                    }
                                }
                                if ($cartItem->cart_product_type == 'combo') {
                                    if (trim($proDetails['combo_product'][0]->availability) == 0 && !is_null($proDetails['combo_product'][0]->availability)) {
                                        updateDetails(['is_saved_for_later' => '1'], ['id' => $cart_user_data[$index]->id], Cart::class);
                                        unset($cart_user_data[$index]);
                                        continue;
                                    }

                                    if (!empty($proDetails['combo_product'])) {
                                        $cart_user_data[$index]->product_details = $proDetails['combo_product'];
                                    } else {
                                        deleteDetails(['id' => $cart_user_data[$index]->id], Cart::class);
                                        unset($cart_user_data[$index]);
                                        continue;
                                    }
                                }
                            } else {
                                deleteDetails(['id' => $cart_user_data[$index]->id], Cart::class);
                                unset($cart_user_data[$index]);
                                continue;
                            }
                        } else {
                            deleteDetails(['id' => $cart_user_data[$index]->id], Cart::class);
                            unset($cart_user_data[$index]);
                            continue;
                        }
                        $local_user_cart[] = $cart[$index];
                    }
                }
                // dd($local_user_cart);

                // dd($res['sub_total']);
                if (isset($res['sub_total']) && !empty($res['sub_total'])) {
                    $delivery_charge_settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
                    // dd($delivery_charge_settings);
                    // dd($local_user_cart);
                    $delivery_charge = app(DeliveryService::class)->getDeliveryCharge(request('address_id'), $res['sub_total'], $local_user_cart, $store_id);
                    //    dd($delivery_charge);
                    if ((isset($delivery_charge_settings[0]->delivery_charge_type) && !empty($delivery_charge_settings[0]->delivery_charge_type) && $delivery_charge_settings[0]->delivery_charge_type == 'zipcode_wise_delivery_charge') || (isset($delivery_charge_settings[0]->delivery_charge_type) && !empty($delivery_charge_settings[0]->delivery_charge_type) && $delivery_charge_settings[0]->delivery_charge_type == 'city_wise_delivery_charge') || (isset($delivery_charge_settings[0]->delivery_charge_type) && !empty($delivery_charge_settings[0]->delivery_charge_type) && $delivery_charge_settings[0]->delivery_charge_type == 'global_delivery_charge')) {
                        // dd('here');
                        for ($i = 0; $i < count($tmpCartUserData); $i++) {
                            $cart_user_data[$i]->product_delivery_charge = isset($delivery_charge[$i]['delivery_charge']) && !empty($delivery_charge[$i]['delivery_charge']) ? $delivery_charge[$i]['delivery_charge'] : '';
                        }
                    } else {
                        $delivery_charge = app(DeliveryService::class)->getDeliveryCharge(request('address_id'), $res['sub_total'], $local_user_cart, $store_id);
                        $total_delivery_charge = 0;

                        // Loop through cart user data
                        for ($i = 0; $i < count($tmpCartUserData); $i++) {
                            // Get individual delivery charge
                            $cart_user_data[$i]->product_delivery_charge = isset($delivery_charge[$i]['delivery_charge']) && !empty($delivery_charge[$i]['delivery_charge'])
                                ? (float) $delivery_charge[$i]['delivery_charge']
                                : 0;

                            // Add to total delivery charge
                            $total_delivery_charge += $cart_user_data[$i]->product_delivery_charge;

                            // Format with currency function
                            $cart_user_data[$i]->currency_product_delivery_charge_data = app(CurrencyService::class)->getPriceCurrency($cart_user_data[$i]->product_delivery_charge);
                        }

                        // Assign the total delivery charge **after** the loop
                        $delivery_charge = strval($total_delivery_charge);
                    }
                }
                // dd($res['delivery_charge']);
                $response['error'] = false;
                $response['message'] = 'Cart Updated !';
                $response['language_message_key'] = 'cart_updated';

                $response['data'] = [
                    'item_total' => strval($res['item_total']),
                    'sub_total' => strval($res['sub_total']),
                    'discount' => strval($res['discount']),
                    'cart' => (isset($cart_user_data) && !empty($cart_user_data)) ? $cart_user_data : [],
                    'total_quantity' => ($qty == 0) ? '0' : strval($qty),
                    'delivery_charge' => !empty($delivery_charge) ? $delivery_charge : $res['delivery_charge'],
                    'currency_delivery_charge_data' => app(CurrencyService::class)->getPriceCurrency($res['delivery_charge']),
                    'currency_sub_total_data' => app(CurrencyService::class)->getPriceCurrency($res['sub_total']),
                    'total_items' => (isset($res[0]->total_items)) ? strval($res[0]->total_items) : "0",
                    'tax_percentage' => (isset($res['tax_percentage'])) ? strval($res['tax_percentage']) : "0",
                    'tax_amount' => (isset($res['tax_amount'])) ? strval($res['tax_amount']) : "0",
                    'currency_tax_amount_data' => app(CurrencyService::class)->getPriceCurrency(isset($res['tax_amount']) ? strval($res['tax_amount']) : "0"),
                    'cart_count' => (isset($res[0]->cart_count)) ? strval($res[0]->cart_count) : "0",
                    'max_items_cart' => $settings['maximum_item_allowed_in_cart'],
                    'overall_amount' => $res['overall_amount'],
                    'currency_overall_amount_data' => app(CurrencyService::class)->getPriceCurrency($res['overall_amount']),
                ];
                return response()->json($response);
            }
        }
    }

    public function clear_cart()
    {
        if (auth()->check()) {
            $user_id = auth()->user()->id;
        } else {
            $response = [
                'error' => true,
                'message' => 'Please Login first.',
                'language_message_key' => 'please_login_first',
                'code' => 102,
            ];
            return response()->json($response);
        }
        deleteDetails(['user_id' => $user_id, 'is_saved_for_later' => 0], Cart::class);
        $response = [
            'error' => false,
            'message' => 'Data removed successfully',
            'language_message_key' => 'data_removed_successfully',
        ];
        return response()->json($response);
    }

    public function get_orders(Request $request)
    {
        /*
            offset:0
            active_status: received  {received,delivered,cancelled,processed,returned}     // optional
            limit:25           // { default - 0 } optional
            sort: id / date_added // { default - id } optional
            order:DESC/ASC      // { default - DESC } optional
            download_invoice:0 // { default - 0 } optional
        */
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
            'limit' => 'numeric',
            'offset' => 'numeric',
            'download_invoice' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $limit = request('limit', 25);
            $order_id = request('id') != null ? request('id') : "";
            $offset = request('offset', 0);
            $sort = request('sort', 'o.id');
            $order = request('order', 'DESC');
            $search = request('search', '');
            $start_date = request('start_date', '');
            $end_date = request('end_date', '');
            $download_invoice = request('download_invoice', 1);
            $store_id = request('store_id') != null ? request('store_id') : "";
            $multiple_status = request()->has('active_status') ? explode(',', request('active_status')) : '';
            $store_id = request('store_id') != null ? request('store_id') : "";
            $language_code = $request->attributes->get('language_code');
            // dd($order_id);
            $order_details = app(OrderService::class)->fetchOrders($order_id, $user_id, $multiple_status, NULL, $limit, $offset, $sort, $order, $download_invoice, $start_date, $end_date, $search, NULL, NULL, NULL, '', true, $store_id, $language_code);


            if (!$order_details['order_data']->isEmpty()) {
                $response = [
                    'error' => false,
                    'message' => 'Data retrieved successfully',
                    'language_message_key' => 'data_retrieved_successfully',
                    'total' => $order_details['total'],
                    'data' => $order_details['order_data'],
                ];
            } else {
                $response = [
                    'error' => true,
                    'message' => 'No Order(s) Found !',
                    'language_message_key' => 'no_orders_found',
                    'data' => [],
                ];
            }
            return response()->json($response);
        }
    }

    public function update_order_item_status(Request $request)
    {
        /*
            status: cancelled / returned
            order_item_id:1201
        */

        $rules = [
            'status' => 'required',
            'order_item_id' => 'required|numeric|exists:order_items,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $status = request('status', 25);
            $order_item_id = request('order_item_id', 0);

            $order_item_data = fetchDetails(OrderItems::class, ['id' => $order_item_id], 'order_id');
            $order_method = fetchDetails(Order::class, ['id' => $order_item_data[0]->order_id], 'payment_method');

            if ($order_method[0]->payment_method == 'bank_transfer') {
                $bank_receipt = fetchDetails(OrderBankTransfers::class, ['order_id' => $order_item_data[0]->order_id]);
                $transaction_status = fetchDetails(Transaction::class, ['order_id' => $order_item_data[0]->order_id], 'status');
                if ($status != "cancelled" && (empty($bank_receipt) || (!empty($transaction_status) && strtolower($transaction_status[0]->status) != 'success'))) {
                    $response = [
                        'error' => true,
                        'message' => 'Order Status can not update, Bank verification is remain from transactions.',
                        'language_message_key' => 'order_status_cannot_update_bank_verification_remaining',
                        'data' => [],
                    ];
                    return response()->json($response);
                }
            }

            if ($status == 'returned') {
                $response = app(OrderService::class)->update_order_item($order_item_id, $status, 0, true);
                $order_id = fetchDetails(OrderItems::class, ['id' => $order_item_id], 'order_id');
                $order_id = isset($order_id) && !empty($order_id) ? $order_id[0]->order_id : "";
                $order_details = app(OrderService::class)->fetchOrders($order_id);
                $response['data'] = $order_details['order_data'];
            } else {
                $response = app(OrderService::class)->update_order_item($order_item_id, $status, '', true);
            }
            if ($status != 'returned' && $response['error'] == false) {
                app(OrderService::class)->process_refund($order_item_id, $status, 'order_items');
            }

            if ($status == 'cancelled') {
                $data = fetchDetails(OrderItems::class, ['id' => $order_item_id], ['product_variant_id', 'quantity', 'order_type']);
                $order_id = fetchDetails(OrderItems::class, ['id' => $order_item_id], 'order_id');
                $order_id = !$order_id->isEmpty() ? $order_id[0]->order_id : "";
                $order_details = app(OrderService::class)->fetchOrders($order_id);
                $response['data'] = $order_details['order_data'];

                if ($data[0]->order_type == 'regular_order') {
                    app(ProductService::class)->updateStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
                }
                if ($data[0]->order_type == 'combo_order') {
                    app(ComboProductService::class)->updateComboStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
                }
            }
        }
        return response()->json($response);
    }


    public function get_faqs(Request $request, FaqController $faqController)
    {
        /*
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id   			    // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */

        $rules = [
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->filled('order') ? $request->input('order') : 'DESC';
            $sort = $request->filled('sort') ? $request->input('sort') : 'id';
            $res = $faqController->getFaqs($offset, $limit, $sort, $order);

            $response = [
                'error' => $res['data']->isEmpty() ? true : false,
                'message' => $res['data']->isEmpty() ? 'FAQ(s) Not Found' : 'FAQ(s) Retrieved Successfully',
                'language_message_key' => $res['data']->isEmpty() ? 'faqs_not_found' : 'faqs_retrieved_successfully',
                'total' => $res['total'],
                'data' => $res['data'],
            ];
            return response()->json($response);
        }
    }

    public function get_offer_images(Request $request, CategoryController $categoryController)
    {
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $store_id = request('store_id') != null ? request('store_id') : "";

            $res = fetchDetails(Offer::class, ['store_id' => $store_id], '*');
            $language_code = $request->attributes->get('language_code');
            $i = 0;
            foreach ($res as $row) {

                $res[$i]->image = app(MediaService::class)->getImageUrl($res[$i]->image);
                $res[$i]->title = app(TranslationService::class)->getDynamicTranslation(Offer::class, 'title', $res[$i]->id, $language_code);
                $res[$i]->banner_image = app(MediaService::class)->getImageUrl($res[$i]->banner_image);
                if ($res[$i]->link == null || empty($res[$i]->link)) {
                    $res[$i]->link = '';
                }
                if (strtolower($res[$i]->type) == 'categories') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $cat_res = $categoryController->getCategories($id, 10, 0, 'row_order', 'ASC', '', '', '', '', '', $language_code);
                    $res[$i]->data = $cat_res;
                } else if (strtolower($res[$i]->type) == 'products') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $pro_res = app(ProductService::class)->fetchProduct(NULL, NULL, $id, '', "20", "0", '', '', '', '', '', '', '', '', '', '', $language_code);
                    $res[$i]->data = $pro_res['product'];
                } else if (strtolower($res[$i]->type) == 'combo_products') {
                    $id = (!empty($res[$i]->type_id) && isset($res[$i]->type_id)) ? $res[$i]->type_id : '';
                    $pro_res = app(ComboProductService::class)->fetchComboProduct(NULL, NULL, $id, '20', '0', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
                    $res[$i]->data = $pro_res['combo_product'];
                } else {
                    $res[$i]->data = [];
                }

                $i++;
            }
            $response = [
                'error' => empty($res) ? true : false,
                'message' => empty($res) ? 'Offers Not Found' : 'Offers Retrieved Successfully',
                'language_message_key' => empty($res) ? 'offers_not_found' : 'offers_retrieved_successfully',
                'data' => $res,
            ];
            return response()->json($response);
        }
    }

    public function get_ticket_types()
    {
        $types = TicketType::all();

        if ($types->isEmpty()) {
            return response()->json([
                'error' => true,
                'message' => 'No ticket types found',
                'language_message_key' => 'no_ticket_types_found',
                'data' => []
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Ticket types fetched successfully',
            'language_message_key' => 'ticket_types_fetched_successfully',
            'data' => $types
        ]);
    }

    public function add_ticket(Request $request)
    {
        /*
            ticket_type_id:1
            subject:product_image not displaying
            email:test@gmail.com
            description:its not showing images of products in web
        */
        $rules = [
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'subject' => 'required',
            'email' => 'required|email',
            'description' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }

            $ticket_type_id = $request->ticket_type_id;
            $subject = $request->subject;
            $email = $request->email;
            $description = $request->description;

            $user = User::find($user_id);

            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found!',
                    'language_message_key' => 'user_does_not_exist',
                    'data' => [],
                ]);
            }

            // Create a new ticket
            $ticket = new Ticket();
            $ticket->ticket_type_id = $ticket_type_id;
            $ticket->user_id = $user_id;
            $ticket->subject = $subject;
            $ticket->email = $email;
            $ticket->description = $description;
            $ticket->status = 1;
            $ticket->save();

            $result = Ticket::find($ticket->id);
            $ticket_type = fetchDetails(TicketType::class, ['id' => $ticket->ticket_type_id], 'title');
            $ticket_type = isset($ticket_type) && !empty($ticket_type) ? $ticket_type[0]->title : '';
            $result->ticket_type = $ticket_type;

            if ($result) {
                return response()->json([
                    'error' => false,
                    'message' => 'Ticket Added Successfully',
                    'language_message_key' => 'ticket_added_successfully',
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Ticket Not Added',
                    'language_message_key' => 'ticket_not_added',
                    'data' => [],
                ]);
            }
        }
    }


    public function edit_ticket(Request $request)
    {
        /*
            ticket_id:1
            ticket_type_id:1
            subject:product_image not displying
            email:test@gmail.com
            description:its not showing attachments of products in web
            status:3 or 5 [3 -> resolved, 5 -> reopened]
            [1 -> pending, 2 -> opened, 3 -> resolved, 4 -> closed, 5 -> reopened]
        */

        $rules = [
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'ticket_id' => 'required|exists:tickets,id',
            'subject' => 'required',
            'email' => 'required|email',
            'description' => 'required',
            'status' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $ticket_type_id = request('ticket_type_id');
            $ticket_id = request('ticket_id');
            $subject = request('subject');
            $email = request('email');
            $description = request('description');
            $status = request('status');

            $user = User::find($user_id);

            if (!$user) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found!',
                    'language_message_key' => 'user_does_not_exist',
                    'data' => [],
                ]);
            }

            // Check if the ticket exists
            $ticket = Ticket::where('id', $ticket_id)
                ->where('user_id', $user_id)
                ->first();

            if (!$ticket) {
                return response()->json([
                    'error' => true,
                    'message' => "User id is changed, you cannot update the ticket.",
                    'language_message_key' => 'user_id_changed_cannot_update_ticket',
                    'data' => [],
                ]);
            }

            if ($status == config('constants.RESOLVED') && $ticket->status == config('constants.CLOSED')) {
                return response()->json([
                    'error' => true,
                    'message' => "Current status is closed.",
                    'language_message_key' => 'current_status_is_closed',
                    'data' => [],
                ]);
            }

            if ($status == 'REOPEN' && ($ticket->status == config('constants.PENDING') || $ticket->status == config('constants.OPENED'))) {
                return response()->json([
                    'error' => true,
                    'message' => "Current status is pending or opened.",
                    'language_message_key' => 'current_status_is_pending_or_opened',
                    'data' => [],
                ]);
            }

            // Update the ticket
            $ticket->ticket_type_id = $ticket_type_id;
            $ticket->subject = $subject;
            $ticket->email = $email;
            $ticket->description = $description;
            $ticket->status = $status;
            $ticket->save();

            // Retrieve the updated ticket
            $result = Ticket::find($ticket_id);

            $ticket_type = fetchDetails(TicketType::class, ['id' => $ticket->ticket_type_id], 'title');
            $ticket_type = !$ticket_type->isEmpty() ? $ticket_type[0]->title : '';
            $result->ticket_type = $ticket_type;

            if ($result) {
                return response()->json([
                    'error' => false,
                    'message' => 'Ticket updated successfully',
                    'language_message_key' => 'ticket_updated_successfully',
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Ticket not updated',
                    'language_message_key' => 'ticket_not_updated',
                    'data' => [],
                ]);
            }
        }
    }

    public function get_tickets(Request $request, TicketController $ticketController)
    {
        /*
            ticket_id: 1001                // { optional}
            ticket_type_id: 1001                // { optional}
            status:   [1 -> pending, 2 -> opened, 3 -> resolved, 4 -> closed, 5 -> reopened]// { optional}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id | date_created | last_updated                // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */
        $rules = [
            'ticket_id' => 'numeric',
            'ticket_type_id' => 'numeric',
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }

            $ticket_type_id = request('ticket_type_id', '');
            $ticket_id = request('ticket_id', '');
            $status = request('status', '');
            $search = $request->input('search', '');
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->input('order', 'DESC');
            $sort = $request->input('sort', 'id');

            $response = $ticketController->getTickets($ticket_id, $ticket_type_id, $user_id, $status, $search, $offset, $limit, $sort, $order);
            return response()->json($response);
        }
    }

    public function get_messages(Request $request, TicketController $ticketController)
    {
        /*
            ticket_id: 1001
            user_type: 1001                // { optional}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id | date_created | last_updated                // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */

        $rules = [
            'ticket_id' => 'required|numeric|exists:tickets,id',
            'status' => 'numeric',
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $ticket_id = $request->input('ticket_id', null);
            $search = $request->input('search', null);
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->input('order', 'DESC');
            $sort = $request->input('sort', 'id');
            $data = config('eshop_pro.type');

            $response = $ticketController->getMessages($ticket_id, $user_id, $search, $offset, $limit, $sort, $order, $data, "");
            return response()->json($response);
        }
    }

    public function is_product_delivarable(Request $request)
    {
        /*
            product_id:10
            product_type:regular // {regular / combo}
            zipcode:132456 {{optional based on type of delivery}}
            city : Ahmedabad {{optional based on type of delivery}}
        */

        $rules = [
            'product_id' => 'required|numeric|exists:products,id',
            'product_type' => 'required'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $zipcode = request('zipcode');
            $city = request('city');
            $product_id = request('product_id');
            $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";
            $isPincode = Zipcode::where('zipcode', $zipcode)->exists();
            $isCity = City::where('name->en', $city)
                ->exists();

            if ($isPincode) {
                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
                $is_available = app(DeliveryService::class)->isProductDelivarable('zipcode', $zipcode_id[0]->id, $product_id, $product_type);
                if ($is_available) {
                    $response['error'] = false;
                    $response['message'] = 'Product is deliverable on ' . $zipcode . '.';
                    return response()->json($response);
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Product is not deliverable on ' . $zipcode . '.';
                    return response()->json($response);
                }
            } else if ($isCity) {
                $city_id = fetchDetails(City::class, ['name->en' => $city], 'id');
                $is_available = app(DeliveryService::class)->isProductDelivarable('city', $city_id[0]->id, $product_id, $product_type);
                // $is_available = isProductDelivarableOld('city', $city_id[0]->id, $product_id, $product_type);

                if ($is_available) {
                    $response['error'] = false;
                    $response['message'] = 'Product is deliverable in ' . $city . '.';
                    return response()->json($response);
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Product is not deliverable in ' . $city . '.';
                    return response()->json($response);
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'Cannot deliver to ' . (isset($zipcode) ? $zipcode : $city) . '.';
                return response()->json($response);
            }
        }
    }
    public function is_seller_delivarable(Request $request)
    {
        /*
            seller_id:10
            store_id:10
            zipcode:132456 {{optional based on type of delivery}}
            city : Ahmedabad {{optional based on type of delivery}}
        */

        $rules = [
            'seller_id' => 'required|numeric',
            'store_id' => 'required|numeric'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $zipcode = request('zipcode');
            $city = request('city');
            $seller_id = request('seller_id') ?? "";
            $store_id = request('store_id') ?? "";
            $isPincode = Zipcode::where('zipcode', $zipcode)->exists();
            $isCity = City::where('name->en', $city)
                ->exists();

            if ($isPincode) {
                $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
                $is_available = app(DeliveryService::class)->isSellerDeliverable('zipcode', $zipcode_id[0]->id, $seller_id, $store_id);
                if ($is_available) {
                    $response['error'] = false;
                    $response['message'] = 'Product is deliverable on ' . $zipcode . '.';
                    return response()->json($response);
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Product is not deliverable on ' . $zipcode . '.';
                    return response()->json($response);
                }
            } else if ($isCity) {
                $city_id = fetchDetails(City::class, ['name->en' => $city], 'id');
                $is_available = app(DeliveryService::class)->isSellerDeliverable('city', $city_id[0]->id, $seller_id, $store_id);
                if ($is_available) {
                    $response['error'] = false;
                    $response['message'] = 'Product is deliverable in ' . $city . '.';
                    return response()->json($response);
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Product is not deliverable in ' . $city . '.';
                    return response()->json($response);
                }
            } else {
                $response['error'] = true;
                $response['message'] = 'Cannot deliver to ' . (isset($zipcode) ? $zipcode : $city) . '.';
                return response()->json($response);
            }
        }
    }
    public function check_cart_products_delivarable(Request $request)
    {
        /*
            address_id:10
            store_id:1
        */
        $rules = [
            'address_id' => 'required|numeric|exists:addresses,id',
            'store_id' => 'required|numeric|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            } else {
                $response = [
                    'error' => true,
                    'message' => 'Please Login first.',
                    'language_message_key' => 'please_login_first',
                    'code' => 102,
                ];
                return response()->json($response);
            }
            $store_id = request('store_id') != null ? request('store_id') : '';
            $address_id = $request->input('address_id');
            $area_id = fetchDetails(Address::class, ['id' => $address_id], ['area_id', 'area', 'pincode', 'city']);
            $zipcode = $area_id[0]->pincode;
            $city = $area_id[0]->city;
            // dd($city);
            $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
            // dd($zipcode_id);
            $city_id = fetchDetails(City::class, ['name->en' => $city], 'id');
            // dd($city_id);
            $language_code = $request->attributes->get('language_code');
            if (!$area_id->isEmpty() || !$zipcode_id->isEmpty() || !$city_id->isEmpty()) {
                $settings = app(DeliveryService::class)->getDeliveryChargeSetting($store_id);
                if (isset($settings[0]->product_deliverability_type) && !empty($settings[0]->product_deliverability_type)) {
                    // dd($settings);
                    if ($settings[0]->product_deliverability_type == 'city_wise_deliverability') {
                        $product_delivarable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, '', '', $store_id, $city, $city_id[0]->id ?? '', '', $language_code);
                    } else {

                        $product_delivarable = app(DeliveryService::class)->checkCartProductsDeliverable($user_id, $zipcode, $zipcode_id[0]->id ?? '', $store_id, '', '', '', $language_code);
                    }
                }

                if (!empty($product_delivarable)) {
                    $product_not_delivarable = array_filter($product_delivarable, function ($var) {
                        return ($var['is_deliverable'] == false && $var['product_id'] != null);
                    });
                    $product_not_delivarable = array_values($product_not_delivarable);
                    $product_delivarable = array_filter($product_delivarable, function ($var) {
                        return ($var['product_id'] != null);
                    });
                    if (!empty($product_not_delivarable)) {
                        $response['error'] = true;
                        $response['message'] = "Some of the item(s) are not delivarable on selected address. Try changing address or modify your cart items.";
                        $response['language_message_key'] = "some_items_not_deliverable_on_selected_address_change_the_address";
                        $response['data'] = $product_delivarable;
                        return response()->json($response);
                    } else {
                        $response['error'] = false;
                        $response['message'] = "Product(s) are delivarable.";
                        $response['language_message_key'] = "products_are_deliverable";
                        $response['data'] = $product_delivarable;
                        return response()->json($response);
                    }
                } else {
                    $response['error'] = false;
                    $response['message'] = "Product(s) are not delivarable";
                    $response['language_message_key'] = "products_are_not_deliverable";
                    $response['data'] = array();
                    return response()->json($response);
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Address not available.";
                $response['language_message_key'] = "address_not_available";
                $response['data'] = array();
                return response()->json($response);
            }
        }
    }

    public function get_sellers(Request $request, SellerController $sellerController)
    {
        /*
            store_id:1
            zipcode:1  //{optional}
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id    // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'zipcode' => 'sometimes|string',
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $zipcode = $request->input('zipcode_id', '');
            $search = $request->input('search', null);
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->input('order', 'DESC');
            $sort = $request->input('sort', 'users.id');
            $store_id = $request->input('store_id') ? (int) $request->input('store_id') : '';
            $user_id = $request->input('user_id') ? (int) $request->input('user_id') : '';
            $seller_ids = $request->input('seller_ids', null);
            if (!is_null($seller_ids)) {
                $seller_ids = explode(",", $seller_ids);
            }
            if (isset($zipcode) && !empty($zipcode)) {
                $is_pincode = isExist(['zipcode' => $zipcode], Zipcode::class);
                if ($is_pincode) {
                    $zipcode_ids = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], 'id');
                    $zipcode_id = !$zipcode_ids->isEmpty() ? $zipcode_ids[0]->id : "";
                } else {
                    $response['error'] = true;
                    $response['message'] = 'Sellers Not Found!';
                    $response['language_message_key'] = 'sellers_not_found';
                    $response['data'] = array();
                    return response()->json($response);
                }
            } else {
                $zipcode_id = "";
            }
            $data = $sellerController->getSellers($zipcode_id, $limit, $offset, $sort, $order, $search, '', $store_id, $seller_ids, $user_id);

            return response()->json($data);
        }
    }

    public function get_promo_codes(Request $request, PromoCodeController $PromoCodeController)
    {
        /*
            store_id:1
            search : Search keyword // { optional }
            limit:25                // { default - 25 } optional
            offset:0                // { default - 0 } optional
            sort: id    // { default - id } optional
            order:DESC/ASC          // { default - DESC } optional
        */
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $store_id = request('store_id') != null ? request('store_id') : "";

            $search = $request->input('search', null);
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->input('order', 'DESC');
            $sort = $request->input('sort', 'id');
            $language_code = $request->attributes->get('language_code');
            $data = $PromoCodeController->getPromoCodes($limit, $offset, $sort, $order, $search, $store_id, $language_code);

            return response()->json($data);
        }
    }
    public function get_stores(Request $request, StoreController $StoreController)
    {
        $rules = [
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $search = $request->input('search', null);
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);
            $order = $request->input('order', 'DESC');
            $sort = $request->input('sort', 'id');
            $language_code = $request->attributes->get('language_code');
            // dd($language_code);
            $data = $StoreController->getStores($limit, $offset, $sort, $order, $search, "", $language_code);

            return response()->json($data);
        }
    }

    // public function get_brands(Request $request, BrandController $BrandController)
    // {
    //     $rules = [
    //         'store_id' => 'required|exists:stores,id',
    //         'limit' => 'numeric',
    //         'offset' => 'numeric',
    //     ];
    //     if ($validationResponse= $this->HandlesValidation($request, $rules, [], null, true)) {
    //         return $response;
    //     } else {
    //         $store_id = request('store_id') != null ? request('store_id') : "";
    //         $ids = $request->filled('ids') ? $request->input('ids') : '';
    //         $search = $request->input('search', null);
    //         $limit = $request->input('limit', 25);
    //         $offset = $request->input('offset', 0);

    //         $data = $BrandController->get_brand_list($search, $offset, $limit, $store_id, $ids);

    //         return response()->json($data);
    //     }
    // }
    public function get_brands(Request $request, BrandController $BrandController)
    {
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $store_id = request('store_id') ?? "";
            $ids = $request->filled('ids') ? $request->input('ids') : '';
            $search = $request->input('search', null);
            $limit = $request->input('limit', 25);
            $offset = $request->input('offset', 0);

            // Fetch the language ID from the middleware
            $language_code = $request->attributes->get('language_code');

            // Pass language ID to the function if needed
            $data = $BrandController->get_brand_list($search, $offset, $limit, $store_id, $ids, $language_code);

            return response()->json($data);
        }
    }

    public function sign_up(Request $request)
    {
        $rules = [
            'mobile' => 'nullable|sometimes|numeric',
            'email' => 'nullable|sometimes|email',
            'fcm_id' => 'nullable|sometimes',
            'type' => 'nullable|sometimes',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $email = request('email', '');
        $mobile = request('mobile', '');
        $type = request('type', '');
        $where = !empty($mobile) ? ['mobile' => $mobile] : ['email' => $email];

        $res = User::select('id', 'mobile', 'email')
            ->where($where)
            ->whereNotIn('type', ['phone'])
            ->get()
            ->toArray();

        if (!empty($res)) {
            $is_exist = !empty($mobile) ? ['mobile' => $mobile] : ['email' => $email];

            if (User::where($is_exist)->exists()) {
                if (request()->filled('fcm_id')) {
                    $fcm_data = [
                        'fcm_id' => request('fcm_id'),
                        'user_id' => $res[0]['id'],
                    ];
                    $existing_fcm = UserFcm::where('user_id', $res[0]['id'])
                        ->where('fcm_id', request('fcm_id'))
                        ->first();
                    if (!$existing_fcm) {
                        UserFcm::insert($fcm_data);
                    }
                }

                $data = User::where($where)
                    ->where('type', $type)
                    ->whereNotIn('type', ['phone'])
                    ->get();
                unset($data->password);

                $user = $data[0];
                $user_id = $user->id;
                $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user_id], 'fcm_id');

                $fcm_ids_array = array_map(function ($item) {
                    return $item->fcm_id;
                }, $fcm_ids->all());

                $token = $user->createToken('authToken')->plainTextToken;

                if (empty($data->image) || !Storage::exists('USER_IMG_PATH' . $data->image)) {
                    $data->image = asset(Config::get('constants.NO_USER_IMAGE'));
                } else {
                    $data->image = app(MediaService::class)->getImageUrl('USER_IMG_PATH' . $data->image);
                }

                foreach ($data as $value) {
                    $fields = [
                        'id',
                        'ip_address',
                        'username',
                        'password',
                        'email',
                        'image',
                        'balance',
                        'activation_selector',
                        'activation_code',
                        'forgotten_password_selector',
                        'forgotten_password_code',
                        'forgotten_password_time',
                        'remember_selector',
                        'remember_code',
                        'created_on',
                        'last_login',
                        'active',
                        'company',
                        'address',
                        'bonus_type',
                        'bonus',
                        'dob',
                        'country_code',
                        'city',
                        'area',
                        'street',
                        'pincode',
                        'serviceable_zones',
                        'apikey',
                        'is_notification_on',
                        'referral_code',
                        'friends_code',
                        'latitude',
                        'longitude',
                        'type',
                        'front_licence_image',
                        'back_licence_image'
                    ];

                    foreach ($fields as $field) {
                        $user_data[$field] = ($value->$field === null) ? "" : $value->$field;
                    }
                    $user_data['fcm_id'] = $fcm_ids_array;
                }

                if (isset($data['active']) && !empty($data) && $data['active'] == 0) {
                    return response()->json([
                        'error' => true,
                        'message' => 'You are not allowed to login. your account is inactive.',
                        'language_message_key' => 'account_inactive_not_allowed_to_login',
                        'data' => [],
                    ]);
                } else {
                    return response()->json([
                        'error' => false,
                        'message' => 'User Logged in successfully',
                        'language_message_key' => 'user_logged_in_successfully',
                        'token' => $token,
                        'data' => $user_data,
                    ]);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'User does not exist!',
                    'language_message_key' => 'user_does_not_exist',
                    'data' => [],
                ]);
            }
        } else {
            $rules = [
                'type' => 'required',
                'name' => 'required',
                'email' => 'nullable|email',
                'mobile' => 'nullable|numeric|unique:users,mobile',
                'country_code' => 'nullable',
                'fcm_id' => 'nullable',
                'referral_code' => 'nullable|unique:users,referral_code',
                'friends_code' => 'nullable',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
            ];
            if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
                return $response;
            }

            if (request()->filled('friends_code')) {
                $friends_code = request('friends_code');
                $friend = User::where('referral_code', $friends_code)->first();

                if (empty($friend)) {
                    return response()->json([
                        'error' => true,
                        'message' => 'Invalid friends code! Please pass the valid referral code of the inviter',
                        'language_message_key' => 'invalid_friends_code_pass_valid_referral_code',
                        'data' => [],
                    ]);
                }
            }

            $additional_data = [
                'username' => request('name'),
                'mobile' => $mobile,
                'email' => $email,
                'type' => $type,
                'country_code' => request('country_code'),
                'referral_code' => request('referral_code'),
                'friends_code' => request('friends_code'),
                'latitude' => request('latitude'),
                'longitude' => request('longitude'),
                'active' => 1,
                'role_id' => 2,
            ];

            $user_id = User::insertGetId($additional_data);

            // add fcm id in user fcm table

            $fcm_data = [
                'fcm_id' => request('fcm_id'),
                'user_id' => $user_id,
            ];
            $existing_fcm = UserFcm::where('user_id', $user_id)
                ->where('fcm_id', request('fcm_id'))
                ->first();
            if (!$existing_fcm) {
                UserFcm::insert($fcm_data);
            }

            $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);
            $wallet_balnace = isset($settings['wallet_balance_amount']) && !empty($settings['wallet_balance_amount']) ? $settings['wallet_balance_amount'] : '';

            if (isset($settings['wallet_balance_status']) && !empty($settings['wallet_balance_status']) && $settings['wallet_balance_status'] == 1) {
                app(WalletService::class)->updateWalletBalance('credit', $user_id, $wallet_balnace, 'Welcome Wallet Amount Credited for Usre ID  : ' . $user_id);
            }

            $where = !empty($mobile) ? ['mobile' => $mobile] : ['email' => $email];
            $where = !empty($user_id) ? ['id' => $user_id] : '';

            User::where($where)->update(['active' => 1]);
            $data = User::where($where)->get();

            $fcm_ids = fetchDetails(UserFcm::class, ['user_id' => $user_id], 'fcm_id');

            $fcm_ids_array = array_map(function ($item) {
                return $item->fcm_id;
            }, $fcm_ids->all());

            unset($data->password);
            unset($data->apikey);

            $user_data = [];

            foreach ($data as $value) {

                $fields = [
                    'ip_address',
                    'username',
                    'password',
                    'email',
                    'image',
                    'balance',
                    'activation_selector',
                    'activation_code',
                    'forgotten_password_selector',
                    'forgotten_password_code',
                    'forgotten_password_time',
                    'remember_selector',
                    'is_notification_on',
                    'remember_code',
                    'created_on',
                    'last_login',
                    'active',
                    'company',
                    'address',
                    'bonus_type',
                    'bonus',
                    'dob',
                    'country_code',
                    'city',
                    'area',
                    'street',
                    'pincode',
                    'serviceable_zones',
                    'apikey',
                    'referral_code',
                    'friends_code',
                    'latitude',
                    'longitude',
                    'type',
                    'front_licence_image',
                    'back_licence_image'
                ];

                foreach ($fields as $field) {
                    $user_data[$field] = ($value->$field == null) ? "" : $value->$field;
                }
                $user_data['fcm_id'] = $fcm_ids_array;
            }
            $user = $data[0];
            return response()->json([
                'error' => false,
                'message' => 'Registered Successfully',
                'language_message_key' => 'registered_successfully',
                'token' => $user->createToken('authToken')->plainTextToken,
                'data' => $user_data,

            ]);
        }
    }

    public function delete_social_account()
    {
        $user_id = auth()->user()->id;
        $user_data = fetchDetails(User::class, ['id' => $user_id], ['id', 'username', 'role_id']);
        $role_id = $user_data[0]->role_id;

        if ($user_data) {
            $user_roles = fetchDetails(Role::class, ['id' => $role_id]);

            if ($user_roles[0]->id == 2) {
                $status = 'awaiting,received,processed,shipped';
                $multiple_status = explode(',', $status);
                $orders = app(OrderService::class)->fetchOrders('', $user_id, $multiple_status);

                foreach ($orders['order_data'] as $order) {

                    updateDetails(['status' => 'cancelled'], ['id' => $order->id], Order::class);
                    updateDetails(['active_status' => 'cancelled'], ['id' => $order->id], Order::class);

                    updateDetails(['active_status' => 'cancelled'], ['order_id' => $order->id], OrderItems::class);
                    updateDetails(['active_status' => 'cancelled'], ['order_id' => $order->id], OrderItems::class);


                    app(OrderService::class)->process_refund($order->id, 'cancelled', 'orders');

                    $data = fetchDetails(OrderItems::class, ['order_id' => $order->id], ['product_variant_id', 'quantity']);
                    $product_variant_ids = [];
                    $qtns = [];

                    foreach ($data as $d) {
                        $product_variant_ids[] = $d['product_variant_id'];
                        $qtns[] = $d['quantity'];
                    }
                    app(ProductService::class)->updateStock($product_variant_ids, $qtns, 'plus');
                }
                deleteDetails(['id' => $user_id], User::class);
                return response()->json(['error' => false, 'message' => 'User Deleted Successfully', 'language_message_key' => 'user_deleted_successfully']);
            } else {
                return response()->json(['error' => true, 'message' => 'Details do not match', 'language_message_key' => 'details_do_not_match']);
            }
        } else {
            return response()->json(['error' => true, 'message' => 'User not found', 'language_message_key' => 'user_does_not_exist']);
        }
    }

    public function add_product_faqs(Request $request)
    {
        /*
            product_id:1
            question : you question here
            product_type:regular // {regular / combo}
        */

        $rules = [
            'product_id' => 'required|numeric',
            'question' => 'required|string',
            'product_type' => 'required'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $product_id = $request->input('product_id');
        $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";
        $user_id = auth()->user()->id;
        $question = $request->input('question');

        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found!',
                'language_message_key' => 'user_does_not_exist',
                'data' => []
            ]);
        }

        if ($product_type == 'regular') {

            if (!isExist(['id' => $product_id], Product::class)) {
                $response = [
                    'error' => true,
                    'message' => 'Product not available.',
                    'language_message_key' => 'product_not_available',
                    'data' => [],
                ];
                return response()->json($response);
            }
            $product_faqs = new ProductFaq([
                'product_id' => $product_id,
                'user_id' => $user_id,
                'question' => $question,
            ]);

            $product_faqs->save();

            $result = ProductFaq::where('id', $product_faqs->id)
                ->where('product_id', $product_id)
                ->where('user_id', $user_id)
                ->get();
        }
        if ($product_type == 'combo') {
            if (!isExist(['id' => $product_id], ComboProduct::class)) {
                $response = [
                    'error' => true,
                    'message' => 'Product not available.',
                    'language_message_key' => 'product_not_available',
                    'data' => [],
                ];
                return response()->json($response);
            }
            $product_faqs = new ComboProductFaq([
                'product_id' => $product_id,
                'user_id' => $user_id,
                'question' => $question,
            ]);

            $product_faqs->save();

            $result = ComboProductFaq::where('id', $product_faqs->id)
                ->where('product_id', $product_id)
                ->where('user_id', $user_id)
                ->get();
        }

        foreach ($result as $value) {
            $fields = [
                'id',
                'user_id',
                'seller_id',
                'product_id',
                'votes',
                'question',
                'answer',
                'answered_by'
            ];

            foreach ($fields as $field) {
                $faq_data[$field] = ($value->$field == null) ? "" : $value->$field;
            }
        }

        return response()->json([
            'error' => false,
            'message' => 'FAQs added successfully',
            'language_message_key' => 'faqs_added_successfully',
            'data' => $faq_data ? $faq_data : []
        ]);
    }

    public function get_product_faqs(Request $request)
    {

        $rules = [
            'id' => 'nullable|numeric',
            'product_id' => 'nullable|numeric',
            'search' => 'nullable|string',
            'sort' => 'nullable|string',
            'limit' => 'nullable|numeric',
            'offset' => 'nullable|numeric',
            'order' => 'nullable|string',
            'product_type' => 'required'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $id = $request->input('id');
        $product_id = $request->input('product_id');
        $user_id = $request->input('user_id');
        $search = trim($request->input('search'));
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 0);
        $order = $request->input('order', 'DESC');
        $sort = $request->input('sort', 'id');
        $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";

        $query = null;

        if ($product_type == 'regular') {
            if (!isExist(['id' => $product_id], Product::class)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Product not available.',
                    'language_message_key' => 'product_not_available',
                    'data' => [],
                    'total' => 0,
                ]);
            }
            $query = ProductFaq::when($id, function ($query) use ($id) {
                $query->where('id', $id);
            })
                ->when($product_id, function ($query) use ($product_id) {
                    $query->where('product_id', $product_id);
                })
                ->when($user_id, function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                })
                ->when($search, function ($query) use ($search) {
                    $query->where('question', 'like', '%' . $search . '%');
                })->whereNotNull('answer')
                ->where('answer', '!=', '');
        }

        if ($product_type == 'combo') {
            if (!isExist(['id' => $product_id], ComboProduct::class)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Product not available.',
                    'language_message_key' => 'product_not_available',
                    'data' => [],
                    'total' => 0,
                ]);
            }

            $query = ComboProductFaq::when($id, function ($query) use ($id) {
                $query->where('id', $id);
            })
                ->when($product_id, function ($query) use ($product_id) {
                    $query->where('product_id', $product_id);
                })
                ->when($user_id, function ($query) use ($user_id) {
                    $query->where('user_id', $user_id);
                })
                ->when($search, function ($query) use ($search) {
                    $query->where('question', 'like', '%' . $search . '%');
                })->whereNotNull('answer')
                ->where('answer', '!=', '');
        }

        if ($query === null) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid product type.',
                'language_message_key' => 'invalid_product_type',
                'data' => [],
                'total' => 0,
            ]);
        }
        // dd($query->tosql(),$query->getbindings());
        // Get total count of records
        $total = $query->count();

        // Get paginated results
        $result = $query->orderBy($sort, $order)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        foreach ($result as &$item) {
            foreach (['answer'] as $field) {
                if (array_key_exists($field, $item) && $item[$field] === null) {
                    $item[$field] = "";
                }
            }

            foreach (['seller_id'] as $field) {
                if (array_key_exists($field, $item) && $item[$field] === null) {
                    $item[$field] = "";
                }
            }

            // Add username for answered_by field
            if ($item['answered_by'] == 0) {
                $item['answered_by'] = "";
            } else {
                $username = $this->getUserName($item['answered_by']);
                $item['answered_by'] = !empty($username) ? $username : "";
            }
        }

        return response()->json([
            'error' => !empty($result) ? false : true,
            'message' => !empty($result) ? 'Faqs Retrieved Successfully' : 'No FAQs found!',
            'language_message_key' => !empty($result) ? 'faqs_retrieved_successfully' : 'no_faqs_found',
            'total' => $total,
            'data' => $result,
        ]);
    }

    private function getUserName($userId)
    {
        $user = User::find($userId);
        return $user ? $user->username : "";
    }


    public function send_message(Request $request, TicketController $TicketController)
    {

        $rules = [
            'user_type' => 'required|alpha',
            'ticket_id' => 'required|numeric',
            'message' => 'nullable|string',
            'attachments.*' => 'nullable|max:8000',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $user_type = $request->input('user_type');
        $user_id = auth()->user()->id;
        $ticket_id = $request->input('ticket_id');
        $message = $request->input('message', '');

        $user = fetchUsers($user_id);
        if (empty($user)) {
            return response()->json([
                'error' => true,
                'message' => 'User not found!',
                'data' => []
            ]);
        }

        $uploaded_images = [];

        if (!File::exists('storage/tickets')) {
            File::makeDirectory('storage/tickets', 0755, true);
        }

        //code for upload media attachements
        try {
            $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
            $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->id : 1;
            $disk = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';

            $media = StorageType::find($mediaStorageType);

            $mediaIds = [];

            if ($request->hasFile('attachments')) {

                $files = $request->file('attachments');

                foreach ($files as $file) {
                    $mediaItem = $media->addMedia($file)
                        ->sanitizingFileName(function ($fileName) use ($media) {
                            // Replace special characters and spaces with hyphens
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));

                            // Generate a unique identifier based on timestamp and random component
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);

                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('tickets', $disk);

                    $mediaIds[] = $mediaItem->id;

                    if ($disk == 'public') {
                        $uploaded_images[] = 'tickets/' . $mediaItem->file_name;
                    }
                }
            }
            if ($disk == 's3') {
                $media_list = $media->getMedia('tickets');
                for ($i = 0; $i < count($mediaIds); $i++) {
                    $media_url = $media_list[($media_list->count()) - (count($mediaIds) - $i)]->getUrl();

                    $uploaded_images[] = $media_url;

                    Media::destroy($mediaIds[$i]);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }

        $ticket_messages = new TicketMessage([
            'user_type' => $user_type,
            'user_id' => $user_id,
            'ticket_id' => $ticket_id,
            'message' => $message,
            'attachments' => json_encode($uploaded_images) ?? [],
            'disk' => $disk ?? '',
        ]);

        $response = $ticket_messages->save();
        $last_insert_id = $ticket_messages->id;


        if ($response) {
            $type = config('eshop_pro.type');
            $result = $TicketController->getMessages($ticket_id, $user_id, "", "", "1", "id", "DESC", $type, $last_insert_id);

            return response()->json([
                'error' => false,
                'message' => 'Message send successfully',
                'language_message_key' => 'message_send_successfully',
                'data' => $result['data'][0]
            ]);
        }
    }

    public function get_zipcodes(Request $request, AreaController $AreaController)
    {
        $rules = [
            'limit' => 'nullable|numeric',
            'offset' => 'nullable|numeric',
            'search' => 'nullable|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }



        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $search = $request->input('search', '');

        $zipcodes = $AreaController->getZipcodes($search, $limit, $offset);

        return response()->json($zipcodes);
    }

    public function update_order_status(Request $request, OrderController $OrderController)
    {
        $rules = [
            'order_id' => 'required|numeric|exists:orders,id',
            'status' => 'required|in:received,processed,shipped,delivered,cancelled,returned',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $allStatus = ['received', 'processed', 'shipped', 'delivered', 'cancelled', 'returned'];

        if (!in_array(strtolower($request->status), $allStatus)) {
            return response()->json(['error' => true, 'message' => 'Invalid Status supplied', 'language_message_key' => 'invalid_status_supplied', 'data' => []]);
        }

        $order = Order::findOrFail($request->order_id);
        $orderMethod = $order->payment_method;

        if ($orderMethod == 'bank_transfer') {
            $bankReceipt = OrderBankTransfers::where('order_id', $request->order_id)->first();
            $transactionStatus = Transaction::where('order_id', $request->order_id)->value('status');

            if ($request->status != 'cancelled' && (empty($bankReceipt) || strtolower($transactionStatus) != 'success')) {
                return response()->json([
                    'error' => true,
                    'message' => 'Order Status cannot be updated. Bank verification is pending in transactions.',
                    'language_message_key' => 'order_status_cannot_be_updated_bank_verification_pending',
                    'data' => []
                ]);
            }
        }

        $response = $OrderController->update_order_status($request);

        if (trim($request->status) != 'returned') {
            app(OrderService::class)->process_refund($request->order_id, $request->status, 'order_items');
        }

        if (trim($request->status) == 'cancelled') {
            $data = Order::find($request->order_id)->orderItems()->first(['product_variant_id', 'quantity', 'order_type']);

            if ($data[0]->order_type == 'regular_order') {
                app(ProductService::class)->updateStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
            }
            if ($data[0]->order_type == 'combo_order') {
                app(ComboProductService::class)->updateComboStock($data[0]->product_variant_id, $data[0]->quantity, 'plus');
            }
        }
        return $response;
    }

    public function delete_order(Request $request)
    {

        $rules = [
            'order_id' => 'required|numeric|exists:orders,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $order_id = $request->order_id;
        $order_items = fetchDetails(OrderItems::class, ['order_id' => $order_id], ['user_id', 'product_variant_id', 'quantity', 'store_id', 'order_type']);
        $order = app(OrderService::class)->fetchOrders($order_id, false, false, false, false, false, 'o.id', 'DESC');
        foreach ($order_items as $order_item) {
            $cart_data = [
                'user_id' => $order_item->user_id,
                'product_variant_id' => $order_item->product_variant_id,
                'qty' => $order_item->quantity,
                'is_saved_for_later' => 0,
                'store_id' => $order_item->store_id,
                'product_type' => $order_item->order_type == 'regular_order' ? 'regular' : 'combo',
            ];
            $test = Cart::create($cart_data);
        }
        if ($order['order_data'][0]->order_items[0]->status[0][0] == 'awaiting') {
            if ($order['order_data'][0]->order_items[0]->order_type == 'regular_order') {
                app(ProductService::class)->updateStock($order['order_data'][0]->order_items[0]->product_variant_id, $order['order_data'][0]->order_items[0]->quantity, 'plus');
            }
            if ($order['order_data'][0]->order_items[0]->order_type == 'combo_order') {
                app(ComboProductService::class)->updateComboStock($order['order_data'][0]->order_items[0]->product_variant_id, $order['order_data'][0]->order_items[0]->quantity, 'plus');
            }
        }
        if (isset($order['order_data'][0]->wallet_balance) && $order['order_data'][0]->wallet_balance != '' && $order['order_data'][0]->wallet_balance != 0) {
            app(WalletService::class)->updateWalletBalance('credit', $order['order_data'][0]->user_id, $order['order_data'][0]->wallet_balance, 'Wallet Amount Credited for Order ID: ' . $order['order_data'][0]->id);
        }
        Order::where('id', $order_id)->delete();
        OrderItems::where('order_id', $order_id)->delete();
        return response()->json([
            'error' => false,
            'message' => 'Order deleted successfully',
            'language_message_key' => 'order_deleted_successfully',
            'data' => []
        ]);
    }

    public function validate_refer_code(Request $request)
    {

        $rules = [
            'referral_code' => 'required|alpha_num|unique:users,referral_code',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        return response()->json([
            'error' => false,
            'message' => 'Referral Code is available to be used',
            'language_message_key' => 'referral_code_available_to_use',
        ]);
    }

    public function get_notifications(Request $request, NotificationController $NotificationController)
    {

        $rules = [
            'sort' => 'nullable|sometimes|string',
            'limit' => 'nullable|sometimes|numeric',
            'offset' => 'nullable|sometimes|numeric',
            'order' => 'nullable|sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $order = $request->input('order', 'DESC');
        $sort = $request->input('sort', 'id');
        $user_id = $request->input('user_id') ?? "";

        $language_code = $request->attributes->get('language_code');
        $res = $NotificationController->get_notifications($offset, $limit, $sort, $order, $user_id, $language_code);
        return response()->json([
            'error' => empty($res['data']) ? true : false,
            'message' => empty($res['data']) ? 'Notification not found' : 'Notification Retrieved Successfully',
            'language_message_key' => empty($res['data']) ? 'no_notification_found' : 'notification_retrieved_successfully',
            'total' => $res['total'],
            'data' => $res['data'],
        ]);
    }

    public function add_transaction(Request $request, TransactionController $TransactionController)
    {

        $rules = [
            'transaction_type' => 'nullable|string',
            'user_id' => 'required|numeric|exists:users,id',
            'order_id' => 'required',
            'type' => 'required|string',
            'txn_id' => 'required|string',
            'amount' => 'required|numeric',
            'status' => 'required|string',
            'message' => 'required|string',
            'skip_verify_transaction' => 'nullable|string',
            'payment_method' => 'required_if:transaction_type,wallet|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $transaction_type = $request->input('transaction_type');
        $type = $request->input('type') ?? "";
        $user_id = auth()->user()->id;
        $txn_id = $request->input('txn_id');
        $status = $request->input('status');
        $amount = $request->input('amount');

        if ($transaction_type === 'wallet' && $request->input('type') === 'credit') {
            $payment_method = strtolower($request->input('payment_method'));

            $user = fetchUsers($user_id);
            if (empty($user)) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not found!',
                    'language_message_key' => 'user_does_not_exist',
                    'data' => []
                ]);
            }
            $old_balance = isset($user->balance) && $user->balance !== "" ? $user->balance : "";
            $skip_verify_transaction = ($request->input('skip_verify_transaction') != null) ? $request->input('skip_verify_transaction') : false;

            $transaction = fetchDetails(Transaction::class, ['txn_id' => $txn_id]);
            // dd($transaction->isempty());
            if ($transaction->isempty() || (isset($transaction[0]['status']) && strtolower($transaction[0]['status']) != 'success')) {
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Wallet could not be recharged! Transaction has already been added before',
                    'language_message_key' => 'wallet_recharge_transaction_already_added',
                    'amount' => 0,
                    'old_balance' => "$old_balance",
                    'new_balance' => "$old_balance",
                    'data' => $transaction,
                ]);
            }
        }
        $transaction_type = (($request->input('transaction_type') != null) && !empty($request->input('transaction_type'))) ? $request->input('transaction_type') : "transaction";

        $order_item_id = fetchDetails(OrderItems::class, ['order_id' => $request->input('order_id')], ['id', 'sub_total']);

        $transaction_data = [
            'transaction_type' => $transaction_type,
            'user_id' => $user_id,
            'order_id' => $request->input('order_id'),
            'type' => isset($type) && !empty($type) ? $type : $transaction_type,
            'txn_id' => $txn_id,
            'amount' => $amount,
            'status' => $status,
            'message' => $request->input('message'),
        ];

        $res = Transaction::create($transaction_data);

        return response()->json([
            'error' => false,
            'message' => ($transaction_type == "wallet") ? 'Wallet Transaction Added Successfully' : 'Transaction Added Successfully',
            'language_message_key' => ($transaction_type == "wallet") ? 'wallet_transaction_added_successfully' : 'transaction_added_successfully',
            'data' => $res,
        ]);
    }

    public function transactions(Request $request, TransactionController $TransactionController)
    {

        $rules = [
            'transaction_type' => 'sometimes|nullable',
            'type' => 'sometimes|nullable',
            'search' => 'sometimes|nullable',
            'sort' => 'sometimes|nullable',
            'limit' => 'sometimes|nullable|numeric',
            'offset' => 'sometimes|nullable|numeric',
            'order' => 'sometimes|nullable',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $user_id = auth()->user()->id;

        $id = $request->input('id', '');
        $transaction_type = $request->input('transaction_type', 'transaction');
        $type = $request->input('type', '');
        $search = $request->input('search', '');
        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $order = $request->input('order', 'DESC');
        $sort = $request->input('sort', 'id');

        $res = $TransactionController->get_transactions($id, $user_id, $transaction_type, $search, $offset, $limit, $sort, $order, $type);


        if (!$res['data']->isEmpty()) {
            $response = [
                'error' => false,
                'message' => 'Transactions Retrieved Successfully',
                'language_message_key' => 'transactions_retrieved_successfully',
                'total' => $res['total'],
                'balance' => app(WalletService::class)->getUserBalance($user_id),
                'data' => $res['data'],
            ];
        } else {
            $response = [
                'error' => true,
                'message' => 'Transaction Not Exist',
                'language_message_key' => 'transaction_not_exist',
                'data' => [],
            ];
        }
        return response()->json($response);
    }

    public function set_product_rating(Request $request, ProductRatingController $ProductRatingController)
    {
        $rules = [
            'product_id' => 'required|numeric|exists:products,id',
            'rating' => 'required|min:1|max:5',
            'title' => 'required',
            'comment' => 'nullable|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $user_id = auth()->user()->id;
        $request['user_id'] = $user_id;


        $files = ($request->allFiles());
        $response = $ProductRatingController->set_rating($request, $files);
        $rating_data = $ProductRatingController->fetch_rating(($request->input('product_id') != null) ? $request->input('product_id') : '', '', '25', '0', 'id', 'DESC', '', '', '', 'true');

        $rating['product_rating'] = $rating_data['product_rating'];

        return response()->json([
            'error' => false,
            'message' => 'Product Rated Successfully',
            'language_message_key' => 'product_rated_successfully',
            'data' => $rating,
        ]);
    }

    public function get_product_rating(Request $request, ProductRatingController $ProductRatingController)
    {

        $rules = [
            'product_id' => 'required|numeric|exists:products,id',
            'sort' => 'string',
            'limit' => 'numeric',
            'offset' => 'numeric',
            'order' => 'string',
            'has_images' => 'boolean',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $user_id = $request->input('user_id');
        $product_id = $request->input('product_id');

        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $has_images = $request->input('has_images', false);

        // update category click

        $category_id = fetchDetails(Product::class, ['id' => $product_id], 'category_id');

        Category::where('id', $category_id[0]->category_id)->increment('clicks');


        $pr_rating = fetchDetails(Product::class, ['id' => $product_id], 'rating');

        $rating = $request->input('rating') != null ? $request->input('rating') : '';
        $rating = $ProductRatingController->fetch_rating(($request->input('product_id') != null) ? $request->input('product_id') : '', $user_id, $limit, $offset, $sort, $order, '', $has_images, $rating, 'true');
        if (!empty($rating['product_rating'])) {
            $response['error'] = false;
            $response['message'] = 'Rating retrieved successfully';
            $response['language_message_key'] = 'rating_retrieved_successfully';
            $response['no_of_rating'] = (!empty($rating['no_of_rating'])) ? $rating['no_of_rating'] : 0;
            $response['no_of_reviews'] = (!empty($rating['no_of_reviews'])) ? $rating['no_of_reviews'] : 0;
            $response['total'] = $rating['total_reviews'];
            $response['star_1'] = $rating['star_1'];
            $response['star_2'] = $rating['star_2'];
            $response['star_3'] = $rating['star_3'];
            $response['star_4'] = $rating['star_4'];
            $response['star_5'] = $rating['star_5'];
            $response['total_images'] = $rating['total_images'];
            $response['product_rating'] = (!empty($pr_rating)) ? $pr_rating[0]->rating : "0";
            $response['data'] = $rating['product_rating'];

            // Convert stdClass object to array
            $responseDataArray = json_decode(json_encode($response['data']), true);

            // Replace null values with empty strings
            foreach ($responseDataArray as &$item) {
                foreach ($item as $key => $value) {
                    if ($value === null) {
                        $item[$key] = "";
                    }
                }
            }

            // Assign the modified array back to response data
            $response['data'] = $responseDataArray;
        } else {
            $response['error'] = true;
            $response['message'] = 'No ratings found!';
            $response['language_message_key'] = 'no_ratings_found';
            $response['no_of_rating'] = (!empty($rating['no_of_rating'])) ? $rating['no_of_rating'] : 0;
            $response['no_of_reviews'] = (!empty($rating['no_of_reviews'])) ? $rating['no_of_reviews'] : 0;

            $response['star_1'] = $rating['star_1'];
            $response['star_2'] = $rating['star_2'];
            $response['star_3'] = $rating['star_3'];
            $response['star_4'] = $rating['star_4'];
            $response['star_5'] = $rating['star_5'];
            $response['total_images'] = $rating['total_images'];
            $response['product_rating'] = (!empty($pr_rating)) ? $pr_rating[0]->rating : "0";
            $response['data'] = array();
        }
        return $response;
    }
    public function delete_product_rating(Request $request, ProductRatingController $ProductRatingController)
    {
        $rules = [
            'rating_id' => 'required|numeric|exists:product_ratings,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        $rating = $ProductRatingController->delete_rating(($request->input('rating_id') != null) ? $request->input('rating_id') : '');

        if ($rating == true) {
            return response()->json([
                'error' => false,
                'message' => 'Rating Deleted Successfully',
                'language_message_key' => 'rating_deleted_successfully',
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Something Went Wrong',
                'language_message_key' => 'something_went_wrong',
            ]);
        }
    }
    public function check_shiprocket_serviceability(Request $request)
    {
        /*
            product_variant_id:10
            product_type:regular // {regular / combo}
            delivery_pincode:132456
            delivery_city:bhuj
        */
        $rules = [
            'product_variant_id' => 'required|numeric|exists:product_variants,id',
            'delivery_pincode' => 'numeric',
            'product_type' => 'required'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $product_type = request('product_type') != null ? Str::lower(request('product_type')) : "";
        $product_variant_id = ($request->input('product_variant_id') != null) ? $request->input('product_variant_id') : 0;
        $delivery_pincode = ($request->input('delivery_pincode') != null) ? $request->input('delivery_pincode') : 0;
        $delivery_city = ($request->input('delivery_city') != null) ? $request->input('delivery_city') : '';


        if ($product_type == 'regular') {
            $product_id = fetchDetails(Product_variants::class, ['id' => $product_variant_id], 'product_id');
            $product_id = $product_id[0]->product_id;
        }

        if ($product_type == 'combo') {
            $product_id = $product_variant_id;
        }


        $settings = app(SettingService::class)->getSettings('shipping_method', true);
        $settings = json_decode($settings, true);

        $is_pincode = isExist(['zipcode' => $delivery_pincode], Zipcode::class);
        $is_city = isExist(['name' => $delivery_city], City::class);

        if ($is_pincode && isset($settings['local_shipping_method']) && $settings['local_shipping_method'] == 1) {

            $zipcode_id = fetchDetails(Zipcode::class, ['zipcode' => $delivery_pincode], 'id');

            $is_available = app(DeliveryService::class)->isProductDelivarable($type = 'zipcode', $zipcode_id[0]->id, $product_id, $product_type);

            if ($is_available) {
                return response()->json([
                    'error' => false,
                    'message' => 'Product is deliverable on ' . $delivery_pincode,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Product is not deliverable on' . $delivery_pincode,
                ]);
            }
        } else if ($is_city && isset($settings['local_shipping_method']) && $settings['local_shipping_method'] == 1) {

            $city_id = fetchDetails(City::class, ['name->en' => $delivery_city], 'id');

            $is_available = app(DeliveryService::class)->isProductDelivarable($type = 'city', $city_id[0]->id, $product_id, $product_type);

            if ($is_available) {
                return response()->json([
                    'error' => false,
                    'message' => 'Product is deliverable in ' . $delivery_city,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Product is not deliverable in ' . $delivery_city,
                ]);
            }
        } else {
            if (isset($settings['shiprocket_shipping_method']) && $settings['shiprocket_shipping_method'] == 1) {
                if (!empty($product_variant_id) && !empty($delivery_pincode)) {

                    $shiprocket = new Shiprocket();
                    $min_days = $max_days = $delivery_charge_with_cod = $delivery_charge_without_cod = 0;

                    if ($product_type == 'regular') {
                        $product_variant_detail = fetchDetails(Product_variants::class, ['id' => $product_variant_id], ['product_id', 'weight']);
                        $product_detail = fetchDetails(Product::class, ['id' => $product_variant_detail[0]->product_id], 'pickup_location')[0]->pickup_location;
                        if (!$product_detail->isEmpty()) {
                            $product_detail = $product_detail;
                        } else {
                            $product_detail = "";
                        }
                    }

                    if ($product_type == 'combo') {
                        $product_variant_detail = fetchDetails(ComboProduct::class, ['id' => $product_id], ['weight', 'pickup_location']);
                        $product_detail = $product_variant_detail[0]->pickup_location;
                    }

                    if (isset($product_variant_detail[0]->weight) && $product_variant_detail[0]->weight > 15) {
                        return response()->json([
                            'error' => true,
                            'message' => 'More than 15kg weight is not allowed',
                            'language_message_key' => 'more_then_15_kg_weight_is_not_allowed',

                        ]);
                    } else {

                        $pickup_postcode = fetchDetails(PickupLocation::class, ['pickup_location' => $product_detail], 'pincode');

                        $availibility_data = [
                            'pickup_postcode' => $pickup_postcode[0]->pincode,
                            'delivery_postcode' => $delivery_pincode,
                            'cod' => 0,
                            'weight' => $product_variant_detail[0]->weight,
                        ];

                        $check_deliveribility = $shiprocket->check_serviceability($availibility_data);

                        $shiprocket_data = app(ShiprocketService::class)->shiprocketRecomendedData($check_deliveribility);

                        $availibility_data_with_cod = [
                            'pickup_postcode' => $pickup_postcode[0]->pincode,
                            'delivery_postcode' => $delivery_pincode,
                            'cod' => 1,
                            'weight' => $product_variant_detail[0]->weight,
                        ];

                        $check_deliveribility_with_cod = $shiprocket->check_serviceability($availibility_data_with_cod);
                        $shiprocket_data_with_cod = app(ShiprocketService::class)->shiprocketRecomendedData($check_deliveribility_with_cod);

                        if (isset($check_deliveribility['status_code']) && $check_deliveribility['status_code'] == 422) {
                            return response()->json([
                                'error' => true,
                                'message' => 'Invalid Delivery Pincode',
                                'language_message_key' => 'invalid_delivery_pincode',
                            ]);
                        } else {
                            $estimate_data = [
                                'pickup_availability' => $shiprocket_data['pickup_availability'],
                                'courier_name' => $shiprocket_data['courier_name'],
                                'delivery_charge_with_cod' => $shiprocket_data_with_cod['rate'],
                                'delivery_charge_without_cod' => $shiprocket_data['rate'],
                                'estimate_date' => $shiprocket_data['etd'],
                                'estimate_days' => $shiprocket_data['estimated_delivery_days'],
                            ];
                            if (isset($check_deliveribility['status']) && $check_deliveribility['status'] == 200 && !empty($check_deliveribility['data']['available_courier_companies'])) {
                                $estimate_date = $check_deliveribility['data']['available_courier_companies'][0]['etd'];

                                return response()->json([
                                    'error' => false,
                                    'message' => 'Product is deliverable by ' . $estimate_date,
                                    'data' => $estimate_data,
                                ]);
                            } else {


                                return response()->json([
                                    'error' => true,
                                    'message' => 'Product is not deliverable on ' . $delivery_pincode,
                                    'data' => $estimate_data,
                                ]);
                            }
                        }
                    }
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'No product variants found',
                        'language_message_key' => 'no_product_variant_found'
                    ]);
                }
            }
        }
    }

    public function send_withdrawal_request(Request $request)
    {

        $rules = [
            'payment_address' => 'required',
            'amount' => 'required|numeric|gt:0',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $user_id = auth()->user()->id;
        $payment_address = $request->input('payment_address');
        $amount = $request->input('amount');

        $user = User::find($user_id);

        if ($user) {
            if ($amount <= $user->balance) {
                $data = [
                    'user_id' => $user_id,
                    'payment_address' => $payment_address,
                    'payment_type' => 'customer',
                    'amount_requested' => $amount,
                ];

                if (PaymentRequest::create($data)) {
                    $lastAddedRequest = PaymentRequest::latest()->first();

                    if ($lastAddedRequest) {
                        $data = $lastAddedRequest->toArray();
                        $data['created_at'] = Carbon::parse($data['created_at'])->format('Y-m-d H:i:s');
                    }

                    app(WalletService::class)->updateBalance($amount, $user_id, 'deduct');
                    $user = User::find($user_id);

                    return response()->json([
                        'error' => false,
                        'message' => 'Withdrawal Request Sent Successfully',
                        'language_message_key' => 'withdrawel_request_sent_successfully',
                        'amount' => $user->balance,
                        'data' => $data,
                    ]);
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'Cannot send Withdrawal Request. Please try again later.',
                        'language_message_key' => 'cannot_send_withdrawel_request'
                    ]);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'You do not have enough balance to send the withdrawal request.',
                    'language_message_key' => 'you_do_not_have_enough_balance_to_send_withdrawel_request'

                ]);
            }
        }

        return response()->json([
            'error' => true,
            'message' => 'User not found.',
            'language_message_key' => 'user_does_not_exist'
        ]);
    }
    public function get_withdrawal_request(Request $request)
    {

        $rules = [
            'limit' => 'numeric',
            'offset' => 'numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $user_id = auth()->user()->id;
        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $user_data = fetchDetails(PaymentRequest::class, ['user_id' => $user_id], '*', $limit, $offset, $sort, $order);

        $data = array_map(function ($item) {
            $item->remarks = $item->remarks ?? "";
            return $item;
        }, $user_data->all());

        $user_data_count = fetchDetails(PaymentRequest::class, ['user_id' => $user_id], '*');
        return response()->json([
            'error' => empty($data) ? true : false,
            'message' => empty($data) ? 'Withdrawal Request Not Found' : 'Withdrawal Request Retrieved Successfully',
            'language_message_key' => empty($data) ? 'withdrawel_request_not_found' : 'withdrawel_request_retrived_successfully',
            'total' => empty($data) ? 0 : count($user_data_count),
            'data' => $data,
        ]);
    }
    public function send_bank_transfer_proof(Request $request)
    {

        /*
           order_id:5
           attachments[]:file  {optional} {type allowed -> image,video,document,spreadsheet,archive}
       */

        $rules = [
            'order_id' => 'required|numeric|exists:orders,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $order_id = ($request->input('order_id') != null) ? $request->input('order_id') : 0;
        $order = fetchDetails(Order::class, ['id' => $order_id], 'id');

        if ($order->isEmpty()) {
            return response()->json([
                'error' => true,
                'message' => 'Order not found!',
                'language_message_key' => 'order_not_found'
            ]);
        }

        if (!File::exists('storage/bank_transfer_proof')) {
            File::makeDirectory('storage/bank_transfer_proof', 0755, true);
        }

        try {
            $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
            $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->id : 1;
            $disk = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';

            $media = StorageType::find($mediaStorageType);

            $mediaIds = [];

            if ($request->hasFile('attachments')) {

                $files = $request->file('attachments');



                foreach ($files as $file) {
                    $mediaItem = $media->addMedia($file)
                        ->sanitizingFileName(function ($fileName) use ($media) {
                            // Replace special characters and spaces with hyphens
                            $sanitizedFileName = strtolower(str_replace(['#', '/', '\\', ' '], '-', $fileName));

                            // Generate a unique identifier based on timestamp and random component
                            $uniqueId = time() . '_' . mt_rand(1000, 9999);

                            $extension = pathinfo($sanitizedFileName, PATHINFO_EXTENSION);
                            $baseName = pathinfo($sanitizedFileName, PATHINFO_FILENAME);

                            return "{$baseName}-{$uniqueId}.{$extension}";
                        })
                        ->toMediaCollection('bank_transfer_proof', $disk);

                    $mediaIds[] = $mediaItem->id;

                    if ($disk == 'public') {
                        $uploaded_images[] = [
                            'image_path' => 'bank_transfer_proof/' . $mediaItem->file_name,
                        ];
                    }
                }
            }
            if ($disk == 's3') {
                $media_list = $media->getMedia('bank_transfer_proof');
                for ($i = 0; $i < count($mediaIds); $i++) {
                    $media_url = $media_list[($media_list->count()) - (count($mediaIds) - $i)]->getUrl();

                    $uploaded_images[] = [
                        'image_path' => $media_url,
                    ];

                    Media::destroy($mediaIds[$i]);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }

        $data = array(
            'order_id' => $order_id,
            'attachments' => $uploaded_images,
            'disk' => $disk,
        );

        if (app(OrderService::class)->addBankTransferProof($data)) {
            $responseImages = array_map(function ($item) {
                return [
                    'image_path' => asset('storage/' . $item['image_path']),
                ];
            }, $uploaded_images);
            return response()->json([
                'error' => false,
                'message' => 'Bank Transfer Proof Added Successfully!',
                'language_message_key' => 'bank_transfer_proof_added_successfully',
                'data' => [
                    'order_id' => $order_id,
                    'attachments' => $responseImages,
                    'disk' => $disk,
                ],
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Something went wrong',
                'language_message_key' => 'something_went_wrong'
            ]);
        }
    }
    public function download_link_hash(Request $request)
    {

        $rules = [
            'order_item_id' => 'required|numeric|exists:order_items,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }



        $order_item_id = ($request->input('order_item_id') != null) ? $request->input('order_item_id') : '';
        $user_id = auth()->user()->id;

        $order_item_data = fetchDetails(OrderItems::class, ['id' => $order_item_id], '*');


        if ($order_item_data == []) {
            return response()->json([
                'error' => true,
                'message' => 'No orders data found!',
                'language_message_key' => 'no_orders_data_found'
            ]);
        } else {
            $order_id = $order_item_data != '' ? $order_item_data[0]->order_id : 0;
            $transaction_data = fetchDetails(Transaction::class, ['order_id' => $order_id], 'status');
            if (!empty($order_item_id) && !empty($user_id)) {
                if (!empty($order_item_data) && !empty($transaction_data)) {
                    $orderData = $order_item_data[0];
                    $transactionStatus = strtolower($transaction_data[0]->status);

                    if ($order_item_id == $orderData->id && $user_id == $orderData->user_id) {
                        if (in_array($transactionStatus, ['success', 'received'])) {
                            $file = $orderData->hash_link;
                            $url = explode("?", $file)[0];
                            $file_path = preg_match('(http:|https:)', $url) === 1 ? $url : app(MediaService::class)->getMediaImageUrl($url);

                            return response()->json([
                                'error' => false,
                                'message' => 'Data retrieved successfully',
                                'language_message_key' => 'data_retrieved_successfully',
                                'data' => $file_path
                            ]);
                        } else {
                            return response()->json([
                                'error' => true,
                                'message' => 'Transaction is not successful for this order',
                                'language_message_key' => 'transaction_is_not_successful_for_this_order',
                            ]);
                        }
                    } else {
                        return response()->json([
                            'error' => true,
                            'message' => 'You are not authorized to download this file',
                            'language_message_key' => 'you_are_not_authorized_to_download_this_file',
                        ]);
                    }
                } else {
                    return response()->json([
                        'error' => true,
                        'message' => 'No data found for this order',
                        'language_message_key' => 'no_data_found_for_this_order',
                    ]);
                }
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid order item ID or user ID',
                    'language_message_key' => 'invalid_order_item_id_or_user_id',
                ]);
            }
        }
    }

    public function get_offers_sliders(Request $request, CategoryController $CategoryController)
    {
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        $store_id = ($request->input('store_id') != null) ? $request->input('store_id') : '';
        $sliders = OfferSliders::orderBy('id')->where('store_id', $store_id)->get()->toArray();
        $i = 0;
        $language_code = $request->attributes->get('language_code');
        if ($sliders) {
            foreach ($sliders as $slider) {
                $offer_ids = $slider['offer_ids'];
                $offer_ids = explode(",", $offer_ids);
                $sliders[$i]['banner_image'] = app(MediaService::class)->getMediaImageUrl($slider['banner_image']);
                $sliders[$i]['title'] = app(TranslationService::class)->getDynamicTranslation(OfferSliders::class, 'title', $sliders[$i]['id'], $language_code);
                $offer_data = [];

                if (!empty($offer_ids)) {
                    $offer_data = Offer::whereIn('id', $offer_ids)
                        ->orderByRaw('FIELD(id, ' . $slider['offer_ids'] . ')')
                        ->get()
                        ->toArray();
                }

                $sliders[$i]['offer_images'] = $offer_data;

                for ($j = 0; $j < count($sliders[$i]['offer_images']); $j++) {
                    $sliders[$i]['offer_images'][$j]['link'] = (isset($sliders[$i]['offer_images'][$j]['link']) && !empty($sliders[$i]['offer_images'][$j]['link'])) ? $sliders[$i]['offer_images'][$j]['link'] : "";
                    $sliders[$i]['offer_images'][$j]['title'] = app(TranslationService::class)->getDynamicTranslation(Offer::class, 'title', $sliders[$i]['offer_images'][$j]['id'], $language_code);
                    $sliders[$i]['offer_images'][$j]['min_discount'] = (isset($sliders[$i]['offer_images'][$j]['min_discount']) && !empty($sliders[$i]['offer_images'][$j]['min_discount'])) ? $sliders[$i]['offer_images'][$j]['min_discount'] : "";
                    $sliders[$i]['offer_images'][$j]['max_discount'] = (isset($sliders[$i]['offer_images'][$j]['max_discount']) && !empty($sliders[$i]['offer_images'][$j]['max_discount'])) ? $sliders[$i]['offer_images'][$j]['max_discount'] : "";
                    $sliders[$i]['offer_images'][$j]['image'] = (isset($sliders[$i]['offer_images'][$j]['image']) && !empty($sliders[$i]['offer_images'][$j]['image'])) ? app(MediaService::class)->getMediaImageUrl($sliders[$i]['offer_images'][$j]['image']) : "";
                    $sliders[$i]['offer_images'][$j]['banner_image'] = (isset($sliders[$i]['offer_images'][$j]['banner_image']) && !empty($sliders[$i]['offer_images'][$j]['banner_image'])) ? app(MediaService::class)->getMediaImageUrl($sliders[$i]['offer_images'][$j]['banner_image']) : "";

                    if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'categories') {
                        $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                        $cat_res = $CategoryController->getCategories($id);
                        $cat_res = $cat_res->original;
                        $sliders[$i]['offer_images'][$j]['category_data'] = $cat_res['categories'][0];
                    } else if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'products') {
                        $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                        $pro_res = app(ProductService::class)->fetchProduct(NULL, NULL, $id, '', '20', '0', '', '', '', '', '', '', '', '', '', '', $language_code);
                        $sliders[$i]['offer_images'][$j]['data'][0]['id'] = $pro_res['product'][0]->id;
                        $sliders[$i]['offer_images'][$j]['data'][0]['image'] = app(MediaService::class)->getMediaImageUrl($pro_res['product'][0]->image);
                    } else if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'combo_products') {
                        $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                        $pro_res = app(ComboProductService::class)->fetchComboProduct(NULL, NULL, $id, '20', '0', '', '', '', '', '', $store_id, '', '', '', '', $language_code);
                        $sliders[$i]['offer_images'][$j]['data'][0]['id'] = $pro_res['combo_product'][0]->id;
                        $sliders[$i]['offer_images'][$j]['data'][0]['image'] = app(MediaService::class)->getMediaImageUrl($pro_res['combo_product'][0]->image);
                    } else if (strtolower($sliders[$i]['offer_images'][$j]['type']) == 'brand') {
                        $id = (!empty($sliders[$i]['offer_images'][$j]['type_id']) && isset($sliders[$i]['offer_images'][$j]['type_id'])) ? $sliders[$i]['offer_images'][$j]['type_id'] : '';
                        $brand_res = fetchDetails(Brand::class, ["id" => $id], '*');
                        $sliders[$i]['offer_images'][$j]['data'][0]['id'] = $brand_res[0]->id;
                        $sliders[$i]['offer_images'][$j]['data'][0]['name'] = $brand_res[0]->name;
                    }
                }

                $i++;
            }

            return response()->json([
                'error' => false,
                'message' => 'Sliders retrieved successfully',
                'language_message_key' => 'sliders_retrieved_successfully',
                'slider_images' => $sliders,
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'No sliders were found',
                'language_message_key' => 'no_sliders_found'
            ]);
        }
    }

    public function get_categories_sliders(Request $request, CategoryController $CategoryController)
    {
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        $store_id = ($request->input('store_id') != null) ? $request->input('store_id') : '';
        $sliders = CategorySliders::orderBy('id')->where('store_id', $store_id)->get()->toArray();
        $i = 0;

        if ($sliders) {
            $language_code = $request->attributes->get('language_code');
            foreach ($sliders as $slider) {
                $category_ids = $slider['category_ids'];
                $category_ids = explode(",", $category_ids);
                $sliders[$i]['banner_image'] = app(MediaService::class)->getMediaImageUrl($slider['banner_image']);
                $sliders[$i]['title'] = app(TranslationService::class)->getDynamicTranslation(CategorySliders::class, 'title', $sliders[$i]['id'], $language_code);
                $category_data = [];
                if (!empty($category_ids)) {
                    $category_data = Category::whereIn('id', $category_ids)
                        ->orderByRaw('FIELD(id, ' . $slider['category_ids'] . ')')
                        ->get()
                        ->toArray();
                }

                $sliders[$i]['category_data'] = $category_data;

                for ($j = 0; $j < count($sliders[$i]['category_data']); $j++) {
                    $category_id = $sliders[$i]['category_data'][$j]['id'];

                    // Fetch subcategories
                    $subcategories = Category::where('parent_id', $category_id)->get()->toArray();

                    // Count subcategories
                    $sub_category_count = count($subcategories);

                    $sliders[$i]['category_data'][$j]['image'] = (isset($sliders[$i]['category_data'][$j]['image']) && !empty($sliders[$i]['category_data'][$j]['image'])) ? app(MediaService::class)->getMediaImageUrl($sliders[$i]['category_data'][$j]['image']) : "";
                    $sliders[$i]['category_data'][$j]['name'] = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $sliders[$i]['category_data'][$j]['id'], $language_code);
                    $sliders[$i]['category_data'][$j]['banner'] = (isset($sliders[$i]['category_data'][$j]['banner']) && !empty($sliders[$i]['category_data'][$j]['banner'])) ? app(MediaService::class)->getMediaImageUrl($sliders[$i]['category_data'][$j]['banner']) : "";

                    // Add subcategory count and data
                    $sliders[$i]['category_data'][$j]['children_count'] = $sub_category_count;

                    // Append base URL to subcategory images and banners
                    foreach ($subcategories as &$subcategory) {
                        // dd($subcategory);
                        $subcategory['image'] = (isset($subcategory['image']) && !empty($subcategory['image'])) ? app(MediaService::class)->getMediaImageUrl($subcategory['image']) : "";
                        $subcategory['name'] = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $subcategory['id'], $language_code);
                        $subcategory['banner'] = (isset($subcategory['banner']) && !empty($subcategory['banner'])) ? app(MediaService::class)->getMediaImageUrl($subcategory['banner']) : "";
                    }
                    unset($subcategory);

                    $sliders[$i]['category_data'][$j]['children'] = $subcategories;

                    // Remove the 'data' part
                    unset($sliders[$i]['category_data'][$j]['data']);
                }

                $i++;
            }

            return response()->json([
                'error' => false,
                'message' => 'Sliders retrieved successfully',
                'language_message_key' => 'sliders_retrieved_successfully',
                'slider_images' => $sliders,
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'No sliders were found',
                'language_message_key' => 'no_sliders_found'
            ]);
        }
    }



    public function set_combo_product_rating(Request $request, ComboProductRatingController $ComboProductRatingController)
    {
        $rules = [
            'product_id' => 'required|numeric|exists:combo_products,id',
            'title' => 'required',
            'rating' => 'required|min:1|max:5',
            'comment' => 'nullable|string',
            'review_image[]' => 'image|mimes:jpg,png,jpeg,gif|max:8000',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $user_id = auth()->user()->id;
        $request['user_id'] = $user_id;
        $files = ($request->allFiles());

        $response = $ComboProductRatingController->set_rating($request, $files);
        $rating_data = $ComboProductRatingController->fetch_rating(($request->input('product_id') != null) ? $request->input('product_id') : '', '', '25', '0', 'id', 'DESC');

        $rating['product_rating'] = $rating_data['product_rating'];

        return response()->json([
            'error' => false,
            'message' => 'Product Rated Successfully',
            'language_message_key' => 'product_rated_successfully',
            'data' => $rating,
        ]);
    }

    public function get_combo_product_rating(Request $request, ComboProductRatingController $ProductRatingController)
    {

        $rules = [
            'product_id' => 'required|numeric|exists:combo_products,id',
            'user_id' => 'numeric|exists:users,id',
            'sort' => 'string',
            'limit' => 'numeric',
            'offset' => 'numeric',
            'order' => 'string',
            'has_images' => 'boolean',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $user_id = $request->input('user_id');
        $product_id = $request->input('product_id');

        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $has_images = $request->input('has_images', false);


        $pr_rating = fetchDetails(ComboProduct::class, ['id' => $product_id], 'rating');

        $rating = $request->input('rating') != null ? $request->input('rating') : '';
        $rating = $ProductRatingController->fetch_rating(($request->input('product_id') != null) ? $request->input('product_id') : '', $user_id, $limit, $offset, $sort, $order, '', $has_images, $rating, 'true');

        if (!empty($rating['product_rating'])) {
            $response['error'] = false;
            $response['message'] = 'Rating retrieved successfully';
            $response['language_message_key'] = 'ratings_retrived_successfully';
            $response['no_of_rating'] = (!empty($rating['no_of_rating'])) ? $rating['no_of_rating'] : 0;
            $response['no_of_reviews'] = (!empty($rating['no_of_reviews'])) ? $rating['no_of_reviews'] : 0;
            $response['total'] = $rating['total_reviews'];
            $response['star_1'] = $rating['star_1'];
            $response['star_2'] = $rating['star_2'];
            $response['star_3'] = $rating['star_3'];
            $response['star_4'] = $rating['star_4'];
            $response['star_5'] = $rating['star_5'];
            $response['total_images'] = $rating['total_images'];
            $response['product_rating'] = (!empty($pr_rating)) ? $pr_rating[0]->rating : "0";
            $response['data'] = $rating['product_rating'];
        } else {

            $response['error'] = true;
            $response['message'] = 'No ratings found !';
            $response['language_message_key'] = 'no_ratings_found';
            $response['no_of_rating'] = (!empty($rating['no_of_rating'])) ? $rating['no_of_rating'] : 0;
            $response['no_of_reviews'] = (!empty($rating['no_of_reviews'])) ? $rating['no_of_reviews'] : 0;
            $response['star_1'] = $rating['star_1'];
            $response['star_2'] = $rating['star_2'];
            $response['star_3'] = $rating['star_3'];
            $response['star_4'] = $rating['star_4'];
            $response['star_5'] = $rating['star_5'];
            $response['total_images'] = $rating['total_images'];
            $response['product_rating'] = (!empty($pr_rating)) ? $pr_rating[0]->rating : "0";
            $response['data'] = array();

            // $response['error'] = true;
            // $response['message'] = 'No ratings found !';
            // $response['language_message_key'] = 'no_ratings_found';
            // $response['no_of_rating'] = 0;
            // $response['data'] = array();
        }
        return $response;
    }
    public function delete_combo_product_rating(Request $request, ComboProductRatingController $ProductRatingController)
    {

        $rules = [
            'rating_id' => 'required|numeric|exists:combo_product_ratings,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $rating = $ProductRatingController->delete_rating(($request->input('rating_id') != null) ? $request->input('rating_id') : '');

        if ($rating == true) {
            return response()->json([
                'error' => false,
                'message' => 'Rating Deleted Successfully',
                'language_message_key' => 'rating_deleted_successfully',
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Something Went Wrong',
                'language_message_key' => 'something_went_wrong',
            ]);
        }
    }

    public function get_languages(Request $request)
    {
        // Fetch languages from the database
        $languages = Language::select('id', 'language', 'code', 'native_language', 'is_rtl')->get();

        // Convert is_rtl to integer using map
        $languages = $languages->map(function ($language) {
            $language->is_rtl = intval($language->is_rtl);
            return $language;
        });

        // Return the fetched languages
        return response()->json([
            'error' => $languages->isEmpty() ? true : false,
            'message' => $languages->isEmpty() ? 'Languages not found' : 'Languages retrieved successfully',
            'language_message_key' => $languages->isEmpty() ? 'languages_not_found' : 'languages_retrieved_successfully',
            'data' => $languages
        ], 200);
    }

    public function get_language_labels(Request $request)
    {

        $rules = [
            'language_code' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }



        $language_code = $request->input('language_code');
        $labels_file_path = resource_path('lang/' . $language_code . '/admin_labels.php');

        if (!file_exists($labels_file_path)) {
            return response()->json([
                'error' => true,
                'message' => 'Language file not found',
                'language_message_key' => 'language_file_not_found',
                'data' => [],
            ]);
        }

        $labels = include $labels_file_path;
        unset($labels['langcode']);
        return response()->json([
            'error' => empty($labels) ? true : false,
            'message' => empty($labels) ? 'Language labels not found' : 'Language labels retrieved successfully',
            'language_message_key' => empty($labels) ? 'language_labels_not_found' : 'language_labels_retrieved_successfully',
            'data' => $labels,
        ]);
    }


    public function top_sellers(Request $request)
    {
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $store_id = $request->input('store_id');
        $user_id = $request->input('user_id');

        // Step 1: Get sellers from OrderItems that are tied to valid store entries
        $sellerIds = OrderItems::select('seller_id')
            ->distinct()
            ->whereHas('sellerStore', fn($q) => $q->where('store_id', $store_id)->where('status', 1))
            ->pluck('seller_id');

        // Step 2: Get the relevant sellers and eager load everything
        $sellers = SellerStore::with([
            'user',
            'favorites' => fn($q) => $q->where('user_id', $user_id),
            'products' => fn($q) => $q->where('store_id', $store_id)->where('status', 1),
            'comboProducts' => fn($q) => $q->where('store_id', $store_id)->where('status', 1),
        ])
            ->whereIn('seller_id', $sellerIds)
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->get();

        // Step 3: Attach order stats
        $orderItems = OrderItems::whereIn('seller_id', $sellerIds)->get();

        $sellersData = $sellers->map(function ($seller) use ($orderItems, $user_id) {
            $sellerOrders = $orderItems->where('seller_id', $seller->seller_id);
            $delivered = $sellerOrders->where('active_status', 'delivered');

            return (object) [
                'seller_id' => $seller->seller_id,
                'total_commission' => $sellerOrders->sum('seller_commission_amount'),
                'store_logo' => app(MediaService::class)->getMediaImageUrl($seller->logo, 'SELLER_IMG_PATH'),
                'store_name' => $seller->store_name,
                'store_description' => $seller->store_description,
                'user_id' => $seller->user_id,
                'rating' => $seller->rating,
                'no_of_ratings' => $seller->no_of_ratings,
                'store_thumbnail' => app(MediaService::class)->getMediaImageUrl($seller->store_thumbnail, 'SELLER_IMG_PATH'),
                'seller_name' => optional($seller->user)->username,
                'address' => outputEscaping(str_replace("\r\n", ' ', optional($seller->user)->address)),
                'total_sales' => $delivered->isNotEmpty() ? $delivered->sum('sub_total') : null,
                'total_products' => $seller->products->count() + $seller->comboProducts->count(),
                'is_favorite' => $seller->favorites->isNotEmpty() ? 1 : 0,
            ];
        });

        // Step 4: Sort and limit
        $sorted = $sellersData->sortByDesc('total_sales')->values()->take(10);

        return response()->json([
            'error' => $sorted->isEmpty(),
            'message' => $sorted->isEmpty() ? 'Sellers not found' : 'Sellers retrieved successfully',
            'language_message_key' => $sorted->isEmpty() ? 'sellers_not_found' : 'sellers_retrieved_successfully',
            'data' => $sorted,
        ]);
    }


    public function most_selling_products(Request $request)
    {
        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
            'zipcode' => 'sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $store_id = $request->input('store_id');
        $user_id = $request->input('user_id', null);
        $zipcode = $request->input('zipcode', null);
        $language_code = $request->attributes->get('language_code');

        // Step 1: Fetch products with necessary relationships and conditions
        $top_selling_products = Product::with([
            'variants' => function ($q) {
                $q->where('status', 1);
            },
            'sellerStore' => function ($q) {
                $q->where('status', 1);
            },
            'sellerData' => function ($q) {
                $q->where('status', 1);
            },
            'favorites' => function ($q) use ($user_id) {
                if ($user_id) {
                    $q->where('user_id', $user_id);
                }
            },
            'taxInfo'
        ])
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->whereHas('variants', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('sellerStore', function ($q) {
                $q->where('status', 1);
            })
            ->whereHas('sellerData', function ($q) {
                $q->where('status', 1);
            })
            ->withSum('orderItems as total_quantity_sold', 'quantity')
            ->withSum('orderItems as total_sales', 'sub_total')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->get();
        // dd($top_selling_products);

        // Step 2: Weekly sales calculations for best seller flag

        $weekly_sales = Product::where('store_id', $store_id)
            ->with([
                'weeklyOrderItems' => function ($query) {
                    $query->select('product_variant_id', DB::raw('SUM(quantity) as weekly_sale'))
                        ->groupBy('product_variant_id');
                }
            ])
            ->get()
            ->mapWithKeys(function ($product) {
                $totalWeeklySale = $product->weeklyOrderItems->sum('weekly_sale');
                return [$product->id => $totalWeeklySale];
            })
            ->toArray();
        $max_weekly_sale = !empty($weekly_sales) ? max($weekly_sales) : 0;

        // Step 3: Transform products - similar to your existing logic
        $top_selling_products->transform(function ($product) use ($zipcode, $language_code, $max_weekly_sale, $weekly_sales) {
            // Handle deliverable zipcodes and deliverability
            if ($product->deliverable_type != 'NONE' && $product->deliverable_type != 'ALL') {
                $zipcode_ids = explode(",", $product->deliverable_zipcodes);
                $zipcodes = Zipcode::whereIn('id', $zipcode_ids)->pluck('zipcode')->toArray();
                $product->deliverable_zipcodes = implode(",", $zipcodes);
            } else {
                $product->deliverable_zipcodes = '';
            }

            // Check if product is deliverable for the given zipcode
            if (!is_null($zipcode)) {
                $zipcodeDetail = fetchDetails(Zipcode::class, ['zipcode' => $zipcode], '*');
                if (!empty($zipcodeDetail)) {
                    $product->is_deliverable = app(DeliveryService::class)->isProductDelivarable('zipcode', $zipcodeDetail[0]->id, $product->id);
                } else {
                    $product->is_deliverable = false;
                }
            } else {
                $product->is_deliverable = false;
            }

            // If deliverable_type is 1 (probably means deliverable everywhere), mark as deliverable
            if ($product->deliverable_type == 1) {
                $product->is_deliverable = true;
            }

            // Mark new arrivals (created within last 7 days)
            $product->new_arrival = isset($product->created_at) && strtotime($product->created_at) >= strtotime('-7 days');

            // Mark best seller based on weekly sales threshold
            $weeklySale = $weekly_sales[$product->id] ?? 0;
            $product->best_seller = ($max_weekly_sale > 0 && $weeklySale >= ($max_weekly_sale * 0.8));

            // Convert image filename to full URL
            $product->image = app(MediaService::class)->getMediaImageUrl($product->image);
            $product->product_id = intval($product->product_id);
            $product->category_id = intval($product->category_id);
            $product->no_of_ratings = intval($product->no_of_ratings);
            $product->deliverable_type = intval($product->deliverable_type);
            $product->is_prices_inclusive_tax = intval($product->is_prices_inclusive_tax);
            $product->total_sales = intval($product->total_sales);
            $product->is_favorite = intval($product->is_favorite);
            // Translate product name and short description
            $product->product_name = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code);
            $product->short_description = app(TranslationService::class)->getDynamicTranslation(Product::class, 'short_description', $product->id, $language_code);
            $product->tax_percentage = optional($product->taxInfo)->percentage ?? 0;
            // Calculate prices with tax if prices are NOT inclusive of tax
            if (!$product->is_prices_inclusive_tax) {
                $product->special_price = calculatePriceWithTax($product->tax_percentage, $product->special_price);
                $product->price = calculatePriceWithTax($product->tax_percentage, $product->price);
            }

            return $product;
        });
        $top_selling_products = $top_selling_products->map(function ($product) {
            $price = null;
            $special_price = null;
            $is_prices_inclusive_tax = $product->is_prices_inclusive_tax;
            $variant = null;

            if ($product->type == 'simple_product' && $product->variants->isNotEmpty()) {
                $variant = $product->variants->first();
            } elseif ($product->type == 'variable_product' && $product->variants->isNotEmpty()) {
                $variant = $product->variants->sortBy('price')->first();
            }

            // Initialize tax-related fields
            $tax_percentages = null;
            $tax_ids = array_filter(explode(',', $product->tax)); // get array of tax ids

            if (!empty($tax_ids)) {
                $tax_values = Tax::whereIn('id', $tax_ids)->pluck('percentage')->toArray();
                $tax_percentages = !empty($tax_values) ? implode(',', $tax_values) : null;
            }

            if ($variant) {
                $price = $variant->price;
                $special_price = $variant->special_price;

                // Apply tax only if NOT inclusive and percentages exist
                if (!$is_prices_inclusive_tax && !empty($tax_values)) {
                    foreach ($tax_values as $percentage) {
                        $price += $price * ($percentage / 100);
                        $special_price += $special_price * ($percentage / 100);
                    }
                }
            }

            return [
                'product_id' => $product->id,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand,
                'product_name' => $product->product_name,
                'short_description' => $product->short_description,
                'created_at' => $product->created_at,
                'image' => app(MediaService::class)->getMediaImageUrl($product->image),
                'rating' => number_format($product->rating ?? 0, 1),
                'no_of_ratings' => $product->no_of_ratings,
                'special_price' => $special_price,
                'price' => $price,
                'type' => $product->type,
                'tax' => $product->tax,
                'deliverable_zipcodes' => $product->deliverable_zipcodes,
                'deliverable_type' => $product->deliverable_type,
                'is_prices_inclusive_tax' => $product->is_prices_inclusive_tax,
                'tax_percentage' => $tax_percentages,
                'tax_id' => $product->tax,
                'total_quantity_sold' => (string) $product->total_quantity_sold,
                'total_sales' => (int) $product->total_sales,
                'is_favorite' => $product->favorites->isNotEmpty() ? 1 : 0,
                'is_deliverable' => (bool) $product->is_deliverable,
                'new_arrival' => (bool) $product->new_arrival,
                'best_seller' => (bool) $product->best_seller,
            ];
        });

        return response()->json([
            'error' => $top_selling_products->isEmpty(),
            'message' => $top_selling_products->isEmpty() ? 'Top-selling products not found' : 'Top-selling products retrieved successfully',
            'language_message_key' => $top_selling_products->isEmpty() ? 'top_selling_products_not_found' : 'top_selling_products_retrieved_successfully',
            'category_ids' => implode(',', $top_selling_products->pluck('category_id')->unique()->filter()->values()->all()),
            'brand_ids' => implode(',', $top_selling_products->pluck('brand_id')->unique()->filter()->values()->all()),
            'data' => $top_selling_products,
        ]);
    }

    public function most_popular_products(Request $request)
    {
        // Validate request

        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
            'sort' => 'sometimes|string|in:name,id',
            'order' => 'sometimes|string|in:DESC,ASC',
            'limit' => 'sometimes|numeric|min:1',
            'offset' => 'sometimes|numeric|min:0',
            'search' => 'sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        // Extract request parameters
        $store_id = $request->input('store_id');
        $user_id = $request->input('user_id') ?? '';
        $sort = $request->input('sort', 'products.name');
        $order = $request->input('order', 'ASC');
        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $search = $request->input('search', '');

        // Build the query
        $query = Product::with([
            'variants' => function ($q) {
                $q->where('status', 1);
            },
            'ratings',
            'favorites' => function ($q) use ($user_id) {
                $q->where('user_id', $user_id);
            },
            'sellerStore',
            'sellerData',
        ])
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->whereHas('variants')
            ->whereHas('sellerStore')
            ->whereHas('sellerData');

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $top_rated_products = $query->offset($offset)->limit($limit)->get();
        // dd($top_rated_products);

        $language_code = $request->attributes->get('language_code');
        // Transform the image URL
        $top_rated_products = $top_rated_products->map(function ($product) use ($language_code) {
            // Calculate min prices from variants
            $variants = $product->variants;

            $special_price = null;
            $price = null;

            if ($variants->isNotEmpty()) {
                // If simple product (only one variant)
                if ($variants->count() === 1) {
                    $special_price = $variants->first()->special_price ?? null;
                    $price = $variants->first()->price ?? null;
                } else {
                    // Variable product - get min prices
                    $special_price = $variants->min('special_price');
                    $price = $variants->min('price');
                }
            }

            // Calculate tax percentage (assuming tax is stored as comma separated IDs in product->tax)
            $tax_ids = $product->tax ? explode(',', $product->tax) : [];
            $tax_percentages = Tax::whereIn('id', $tax_ids)->pluck('percentage')->toArray();
            $tax_percentage = !empty($tax_percentages) ? implode(',', $tax_percentages) : null;

            return [
                'id' => $product->id,
                'name' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code),
                'short_description' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'short_description', $product->id, $language_code),
                'tax' => $product->tax,
                'category_id' => $product->category_id,
                'brand_id' => $product->brand,  // or brand_id if your attribute is named like that
                'special_price' => calculatePriceWithTax($tax_percentage, $special_price),
                'price' => calculatePriceWithTax($tax_percentage, $price),
                'product_image' => app(MediaService::class)->getMediaImageUrl($product->product_image),
                'rating' => round($product->ratings->avg('rating'), 1) ?? 0,
                'no_of_ratings' => $product->ratings->count(),
                'is_favorite' => $product->favorites->isNotEmpty() ? 1 : 0,
                'tax_percentage' => $tax_percentage ?: null,
                'tax_id' => $product->tax ? $product->tax : null,
            ];
        });

        return response()->json([
            'error' => $top_rated_products->isEmpty() ? true : false,
            'message' => $top_rated_products->isEmpty() ? 'Most popular products not found' : 'Most popular products retrieved successfully',
            'language_message_key' => $top_rated_products->isEmpty() ? 'most_popular_products_not_found' : 'most_popular_products_retrieved_successfully',
            'category_ids' => implode(',', collect($top_rated_products)->pluck('category_id')->unique()->values()->all()),
            'brand_ids' => implode(',', collect($top_rated_products)->pluck('brand_id')->filter()->unique()->values()->all()),
            'data' => $top_rated_products,
        ]);
    }

    public function best_sellers(Request $request)
    {
        /*
           sort:               // { name / id } optional
           order:DESC/ASC      // { default - ASC } optional
           limit:25            // { default - 25 } optional
           offset:0            // { default - 0 } optional
           search:value        // {optional}
        */

        // Validate request

        $rules = [
            'store_id' => 'required|numeric|exists:stores,id',
            'sort' => 'sometimes|string|in:name,id',
            'order' => 'sometimes|string|in:DESC,ASC',
            'limit' => 'sometimes|numeric|min:1',
            'offset' => 'sometimes|numeric|min:0',
            'search' => 'sometimes|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        // Extract request parameters
        $store_id = $request->input('store_id');
        $user_id = $request->input('user_id') ?? '';
        $sort = $request->input('sort', 'seller_store.rating');
        $order = $request->input('order', 'DESC');
        $limit = $request->input('limit', 25);
        $offset = $request->input('offset', 0);
        $search = $request->input('search', '');

        // Build the query

        $query = User::whereHas('sellerStore', function ($q) use ($store_id) {
            $q->where('store_id', $store_id)
                ->where('status', 1)
                ->where('rating', '>', 0)
                ->where('no_of_ratings', '>', 0);
        })
            ->with([
                'sellerStore' => function ($q) use ($store_id) {
                    $q->where('store_id', $store_id)
                        ->select([
                            'user_id',
                            'seller_id',
                            'store_name',
                            'store_description',
                            'logo',
                            'store_thumbnail',
                            'rating',
                            'no_of_ratings',
                            'store_id'
                        ]);
                },
                'sellerStore.favorites' => function ($q) use ($user_id) {
                    $q->where('user_id', $user_id);
                }
            ]);

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', '%' . $search . '%')
                    ->orWhereHas('sellerStore', function ($q2) use ($search) {
                        $q2->where('store_name', 'like', '%' . $search . '%');
                    });
            });
        }

        // Fetch all results
        $users = $query->get();

        // Sort in PHP
        if ($sort == 'name') {
            $users = $order == 'DESC' ? $users->sortByDesc('username') : $users->sortBy('username');
        } elseif ($sort == 'id') {
            $users = $order == 'DESC' ? $users->sortByDesc('id') : $users->sortBy('id');
        } else {
            $users = $order == 'DESC'
                ? $users->sortByDesc(fn($user) => optional($user->sellerStore)->rating)
                : $users->sortBy(fn($user) => optional($user->sellerStore)->rating);
        }

        // Paginate manually
        $best_sellers = $users->slice($offset, $limit)->values();

        $best_sellers = $best_sellers->map(function ($user) use ($store_id, $user_id) {
            $store = $user->sellerStore;

            if (!$store) {
                return null;
            }

            // Count total products
            $total_products = Product::where('seller_id', $store->seller_id)
                ->where('store_id', $store_id)
                ->where('status', 1)
                ->count()
                + ComboProduct::where('seller_id', $store->seller_id)
                    ->where('store_id', $store_id)
                    ->where('status', 1)
                    ->count();

            return [
                'seller_id' => $store->seller_id,
                'user_id' => $user->id,
                'seller_name' => $user->username,
                'store_name' => $store->store_name,
                'store_description' => $store->store_description,
                'store_logo' => app(MediaService::class)->getMediaImageUrl($store->logo, 'SELLER_IMG_PATH'),
                'store_thumbnail' => app(MediaService::class)->getMediaImageUrl($store->store_thumbnail, 'SELLER_IMG_PATH'),
                'rating' => $store->rating,
                'no_of_ratings' => $store->no_of_ratings,
                'total_products' => $total_products,
                'is_favorite' => $store->favorites->isNotEmpty() ? 1 : 0,
            ];
        });

        return response()->json([
            'error' => $best_sellers->isEmpty(),
            'message' => $best_sellers->isEmpty() ? 'Best sellers not found' : 'Best sellers retrieved successfully',
            'language_message_key' => $best_sellers->isEmpty() ? 'best_sellers_not_found' : 'best_sellers_retrieved_successfully',
            'data' => $best_sellers,
        ]);
    }
    public function download_order_invoice(Request $request)
    {
        $rules = [
            'order_id' => 'required|numeric|exists:orders,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        } else {
            $order_id = $request->input('order_id');

            if (!isExist(['id' => $order_id], Order::class)) {
                $response = [
                    'error' => true,
                    'message' => 'No order found!',
                    'language_message_key' => 'no_order_found',
                    'data' => [],
                ];
                return response()->json($response);
            }

            // Generating the URL to download the invoice
            $invoice_url = route('admin.orders.generatInvoicePDF', ['id' => $order_id]);

            $response = [
                'error' => false,
                'message' => 'Invoice URL generated successfully',
                'invoice_url' => $invoice_url,  // Return the generated URL
            ];

            return response()->json($response);
        }
    }


    public function phonepe_app(Request $request)
    {

        /*
            type:wallet/cart  //required
            transaction_id:741258 //required
            mobile:123456478   // required for wallet
            amount:5200   // required for wallet
            order_id:1642 // required for cart
        */

        $rules = [
            'type' => 'required|string',
            'transaction_id' => 'required|numeric',
            'mobile' => 'required_if:type,wallet|numeric',
            'amount' => 'required_if:type,wallet|numeric',
            'order_id' => 'required_if:type,cart|numeric',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }


        $phonepe = new Phonepe();
        if ($request->type == 'wallet') {
            $data = [
                'amount' => $request->amount * 100,
                'mobile' => $request->mobile,
                'order_id' => $request->transaction_id,
                'merchantTransactionId' => $request->order_id,
            ];

            $res = $phonepe->phonepe_checksum_v2($data);

            return response()->json([
                'error' => false,
                'data' => $res,
            ]);
        } else {
            if (auth()->check()) {
                $user_id = auth()->user()->id;
            }
            $order_details = app(OrderService::class)->fetchOrders($request->order_id, $user_id, false, false, false, false, 'o.id', 'DESC');
            if ($order_details['total'] != 0) {
                $transaction_id = time() . "" . rand("100", "999");
                $amount = $order_details['order_data'][0]->total_payable;
                $mobile = $order_details['order_data'][0]->mobile;
                $data = array(
                    // 'merchantTransactionId' => $transaction_id,
                    'merchantTransactionId' => $request->order_id,
                    'merchantUserId' => $user_id,
                    'amount' => $amount * 100,
                    'mobileNumber' => $mobile
                );
                $res = $phonepe->phonepe_checksum_v2($data);

                return response()->json([
                    'error' => false,
                    'data' => $res,
                ]);
            } else {
                return response()->json([
                    'error' => true,
                    'message' => 'Order Not Found',
                ]);
            }
        }
    }

    // public function phonepe_app_old(Request $request)
    // {

    //     /*
    //         type:wallet/cart  //required
    //         transaction_id:741258 //required
    //         mobile:123456478   // required for wallet
    //         amount:5200   // required for wallet
    //         order_id:1642 // required for cart
    //     */

    //     $rules = [
    //         'type' => 'required|string',
    //         'transaction_id' => 'required|numeric',
    //         'mobile' => 'required_if:type,wallet|numeric',
    //         'amount' => 'required_if:type,wallet|numeric',
    //         'order_id' => 'required_if:type,cart|numeric',
    //     ];
    //     if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
    //         return $response;
    //     }


    //     $phonepe = new Phonepe();
    //     if ($request->type == 'wallet') {
    //         $data = [
    //             'final_total' => $request->amount * 100,
    //             'mobile' => $request->mobile,
    //             'order_id' => $request->transaction_id,
    //         ];
    //         $v2_response = $this->phonepe_app_new($request);
    //         $v2_response = $v2_response->original['data'];
    //         // dd($v2_response);
    //         $res = $phonepe->phonepe_checksum($data);

    //         return response()->json([
    //             'error' => false,
    //             'data' => $res,
    //             'v2_response' => $v2_response,
    //         ]);
    //     } else {
    //         if (auth()->check()) {
    //             $user_id = auth()->user()->id;
    //         }
    //         $order_details = app(OrderService::class)->fetchOrders($request->order_id, $user_id, false, false, false, false, 'o.id', 'DESC');
    //         if ($order_details['total'] != 0) {
    //             $amount = $order_details['order_data'][0]->total_payable * 100;
    //             $data = [
    //                 'final_total' => $amount,
    //                 'mobile' => $order_details['order_data'][0]->mobile,
    //                 'order_id' => $request->transaction_id,
    //             ];
    //             $v2_response = $this->phonepe_app_new($request);
    //             $v2_response = $v2_response->original['data'];
    //             $res = $phonepe->phonepe_checksum($data);

    //             return response()->json([
    //                 'error' => false,
    //                 'data' => $res,
    //                 'v2_response' => $v2_response,
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'error' => true,
    //                 'message' => 'Order Not Found',
    //             ]);
    //         }
    //     }
    // }
    public function get_paypal_link(request $request)
    {
        /*
            user_id : 2
            order_id : 1
            amount : 150
        */
        header("Content-Type: text/html");

        $rules = [
            'amount' => 'required',
            'order_id' => 'required',
            'user_id' => 'required|exists:users,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $user_id = $request->user_id;

        $order_id = $request->order_id;
        $amount = $request->amount;

        if (!is_numeric($order_id)) {
            return
                $this->paypal_transaction_webview($user_id, $order_id, $amount);
        }
        return
            $this->paypal_transaction_webview($user_id, $order_id, $amount);
    }
    public function app_payment_status(Request $request)
    {
        $paypalInfo = $request->all();
        if (!empty($paypalInfo) && $request->has('st')) {
            $status = strtolower($request->query('st'));
            switch ($status) {
                case 'completed':
                    $response = [
                        'error' => false,
                        'message' => 'Payment Completed Successfully',
                        'data' => $paypalInfo,
                    ];
                    break;

                case 'authorized':
                    $response = [
                        'error' => false,
                        'message' => 'Your payment has been Authorized successfully. We will capture your transaction within 30 minutes, once we process your order. After successful capture, coins will be credited automatically.',
                        'data' => $paypalInfo,
                    ];
                    break;

                case 'pending':
                    $response = [
                        'error' => false,
                        'message' => 'Your payment is pending and is under process. We will notify you once the status is updated.',
                        'data' => $paypalInfo,
                    ];
                    break;

                default:
                    $response = [
                        'error' => true,
                        'message' => 'Payment Cancelled / Declined',
                        'data' => $paypalInfo,
                    ];
                    break;
            }
        } else {
            $response = [
                'error' => true,
                'message' => 'Payment Cancelled / Declined',
                'data' => $paypalInfo,
            ];
        }

        return response()->json($response);
    }
    public function paypal_transaction_webview($user_id, $order_id, $amount)
    {
        $user = User::find($user_id);

        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found',
                'data' => []
            ]);
        }

        // Retrieve the order
        $order = app(OrderService::class)->fetchOrders($order_id);

        if ($order['order_data']->isEmpty()) {
            $data['user'] = $user;
            $data['payment_type'] = 'paypal';
            $returnURL = route('app_payment_status');
            $cancelURL = route('app_payment_status');
            $notifyURL = route('ipn');
            $txn_id = time() . '-' . rand();
            $payeremail = $user->email;
            $paypal = new PayPal();

            $paypal->addField('return', $returnURL);
            $paypal->addField('cancel_return', $cancelURL);
            $paypal->addField('notify_url', $notifyURL);
            $paypal->addField('item_name', 'Test');
            $paypal->addField('custom', $user_id . '|' . $payeremail);
            $paypal->addField('item_number', $order_id);
            $paypal->addField('amount', $amount);

            // Render paypal form
            $data = $paypal->paypal_auto_form();
        } else {
            $data['user'] = $user;
            $data['order'] = !empty($order['order_data']) ? $order['order_data'][0] : '';
            $data['payment_type'] = 'paypal';
            $returnURL = route('app_payment_status');
            $cancelURL = route('app_payment_status');
            $notifyURL = route('ipn');
            $txn_id = time() . '-' . rand();
            $payeremail = $user->email;
            $paypal = new PayPal();

            $paypal->addField('return', $returnURL);
            $paypal->addField('cancel_return', $cancelURL);
            $paypal->addField('notify_url', $notifyURL);
            $paypal->addField('item_name', 'Test');
            $paypal->addField('custom', $user_id . '|' . $payeremail);
            $paypal->addField('item_number', $order_id);
            $paypal->addField('amount', $amount);

            // Render paypal form
            $data = $paypal->paypal_auto_form();
        }
    }




    public function get_similar_products(Request $request)
    {

        $rules = [
            'category_id' => 'required|exists:categories,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $store_id = $request->input('store_id');
        $order = $request->filled('p_order') ? $request->input('p_order', 'DESC') : 'DESC';
        $sort = $request->filled('p_sort') ? $request->input('p_sort', 'p.id') : 'products.id';
        $limit = $request->filled('limit') ? $request->input('limit', 10) : 10;
        $offset = $request->filled('offset') ? $request->input('offset', 0) : 0;
        $category_id = $request->input('category_id', null);
        $user_id = $request->input('user_id', '');
        $language_code = $request->attributes->get('language_code');
        $products = app(ProductService::class)->fetchProduct($user_id, '', '', $category_id, $limit, $offset, $sort, $order, null, '', '', '', $store_id, '1', '', '', $language_code);
        if (!empty($products['product'])) {
            $response = [
                'error' => false,
                'message' => 'Products retrieved successfully!',
                'language_message_key' => 'products_retrived_successfully',
                'min_price' => isset($products['min_price']) && !empty($products['min_price']) ? strval($products['min_price']) : '0',
                'max_price' => isset($products['max_price']) && !empty($products['max_price']) ? strval($products['max_price']) : '0',
                'filters' => isset($products['filters']) && !empty($products['filters']) ? $products['filters'] : [],
                'tags' => !empty($tags) ? $tags : [],
                'total' => isset($products['total']) ? strval($products['total']) : '',
                'offset' => $offset,
                'data' => $products['product'],
            ];
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Products Not Found !',
                'language_message_key' => 'products_not_found',
                'data' => [],
            ], 200);
        }
        return response()->json($response);
    }
    public function get_combo_similar_products(Request $request)
    {
        $rules = [
            'product_id' => 'required|exists:combo_products,id',
            'store_id' => 'required|exists:stores,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $store_id = $request->input('store_id');

        $combo_product = ComboProduct::where('id', $request->input('product_id'))
            ->first();

        if (!$combo_product) {
            return response()->json([
                'error' => true,
                'message' => 'Combo product not found.',
                'code' => 102
            ]);
        }
        $order = $request->filled('order') ? $request->input('order', 'DESC') : 'DESC';
        $sort = $request->filled('sort') ? $request->input('sort', 'p.id') : 'p.id';
        $limit = $request->filled('limit') ? $request->input('limit', 10) : 10;
        $offset = $request->filled('offset') ? $request->input('offset', 0) : 0;

        $product_ids = ComboProduct::where('id', $combo_product->id)
            ->pluck('product_ids')->first();

        $product_ids = explode(',', $product_ids);

        $categoryIds = Product::whereIn('id', $product_ids)
            ->pluck('category_id');
        $category_id = $categoryIds->toArray();
        if ($categoryIds->isEmpty()) {
            return response()->json([
                'error' => true,
                'message' => 'No categories found for the products in the combo.',
                'code' => 102
            ]);
        }
        $language_code = $request->attributes->get('language_code');

        $similar_products = app(ComboProductService::class)->fetchComboProduct('', '', '', $limit, $offset, $sort, $order, '', '', '', $store_id, $category_id, '', '', '', $language_code);
        // dD($similar_products['combo_product']);
        return response()->json([
            'error' => false,
            'message' => empty($similar_products['combo_product']) ? 'Products not found' : 'Products retrieved successfully',
            'language_message_key' => empty($similar_products['combo_product']) ? 'products_not_found' : 'products_retrieved_successfully',
            'data' => empty($similar_products['combo_product']) ? [] : $similar_products,
            'code' => 200,
        ]);
    }

    public function search_products(Request $request)
    {
        // Validate the request
        $rules = [
            'store_id' => 'required|exists:stores,id',
            'search' => 'required|string',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        $search = trim($request->input('search'));
        $store_id = $request->input('store_id');
        $keywords = explode(' ', $search);
        $language_code = $request->attributes->get('language_code');

        // Search Products using Eloquent
        $products = Product::with('category')
            ->where('store_id', $store_id)
            ->where('status', 1)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $keyword = strtolower($keyword);
                    $query->where(function ($subQuery) use ($keyword) {
                        $subQuery->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])
                            ->orWhereHas('category', function ($q) use ($keyword) {
                                $q->whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"]);
                            });
                    });
                }
            })->get();

        // Search Combo Products using Eloquent
        $comboProducts = ComboProduct::where('store_id', $store_id)
            ->where('status', 1)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $keyword = strtolower($keyword);
                    $query->whereRaw('LOWER(title) LIKE ?', ["%{$keyword}%"]);
                }
            })->get();

        // Transform products
        $productsTransformed = $products->map(function ($product) use ($language_code) {
            return (object) [
                'type' => 'products',
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'product_name' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code),
                'product_image' => app(MediaService::class)->getMediaImageUrl($product->image),
                'category_id' => $product->category_id,
                'category_name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $product->category_id, $language_code),
            ];
        });

        // Transform combo products
        $comboTransformed = $comboProducts->map(function ($combo) use ($language_code) {
            return (object) [
                'type' => 'combo_products',
                'product_id' => $combo->id,
                'store_id' => $combo->store_id,
                'product_name' => app(TranslationService::class)->getDynamicTranslation(ComboProduct::class, 'title', $combo->id, $language_code),
                'product_image' => app(MediaService::class)->getMediaImageUrl($combo->image),
            ];
        });

        // Merge both collections
        $results = $productsTransformed->merge($comboTransformed)->values();

        return response()->json([
            'error' => $results->isEmpty(),
            'message' => $results->isEmpty() ? 'Products not found' : 'Products retrieved successfully',
            'language_message_key' => $results->isEmpty() ? 'products_not_found' : 'products_retrieved_successfully',
            'data' => $results,
        ]);
    }


    public function get_most_searched_history(Request $request)
    {
        $searchTerm = trim($request->input('search'));
        $storeId = $request->input('store_id');

        $rules = [
            'search' => 'string|max:255',
            'store_id' => 'required|integer',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }

        // Find or create the search history record
        $searchHistory = SearchHistory::firstOrNew([
            'search_term' => $searchTerm,
            'store_id' => $storeId,
        ]);

        // Increment clicks or set to 1 if new
        $searchHistory->clicks = $searchHistory->exists ? $searchHistory->clicks + 1 : 1;
        $searchHistory->save();

        // Fetch most searched terms
        $mostSearchedTerms = SearchHistory::where('store_id', $storeId)
            ->orderByDesc('clicks')
            ->limit(10)
            ->get(['search_term', 'clicks']);

        return response()->json([
            'error' => false,
            'message' => 'Search terms fetched successfully.',
            'data' => $mostSearchedTerms,
        ]);
    }
    public function razorpay_create_order(Request $request)
    {

        $rules = [
            'order_id' => 'required',
            'amount' => 'required',
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $order_id = $request->input('order_id') ?? '';
        $order = app(OrderService::class)->fetchOrders($order_id, '', '', '', 1, 0, 'o.id', 'DESC');
        $currency = fetchDetails(Currency::class, ['is_default' => 1]);
        $currency = isset($currency) && !empty($currency) ? $currency[0]->code : "";
        if (!empty($order) && !empty($currency) && is_numeric($order_id)) {
            $price = $order['order_data'][0]->total_payable;
            $amount = intval($price * 100);
            $razorpay = new Razorpay();
            $create_order = $razorpay->create_order($amount, $order_id, $currency);
            if (!empty($create_order)) {
                return response()->json([
                    'error' => false,
                    'message' => 'Razorpay order created successfully.',
                    'language_message_key' => 'order_created_successfully',
                    'data' => $create_order,
                ]);
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Razorpay order not created.',
                    'language_message_key' => 'something_went_wrong',
                    'data' => array(),
                ]);
            }
        } elseif ((!is_numeric($order_id) && strpos($order_id, "wallet-refill-user") !== false)) {
            $amount = $request->input('amount') ?? '';
            $amount = intval($amount * 100);
            $razorpay = new Razorpay();
            $create_order = $razorpay->create_order($amount, $order_id, $currency);
            if (!empty($create_order)) {
                return response()->json([
                    'error' => false,
                    'message' => 'Razorpay order created successfully.',
                    'language_message_key' => 'order_created_successfully',
                    'data' => $create_order,
                ]);
            } else {
                return response()->json([
                    'error' => false,
                    'message' => 'Razorpay order not created.',
                    'language_message_key' => 'something_went_wrong',
                    'data' => array(),
                ]);
            }
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Details not found.',
                'language_message_key' => 'no_order_found',
                'data' => array(),
            ]);
        }
    }
    public function get_zones(Request $request)
    {
        $request['language_code'] = $request->attributes->get('language_code');
        return getZones($request);
    }
    public function paystack_webview(Request $request)
    {

        $rules = [
            'amount' => 'required|numeric'
        ];
        if ($response = $this->HandlesValidation($request, $rules, [], null, true)) {
            return $response;
        }
        $user = Auth::user();
        $email = fetchDetails(User::class, ['id' => $user->id], 'email');
        $email = isset($email) && !empty($email) ? $email[0]->email : '';

        $paystack = new Paystack();
        $data = [
            'user_id' => $user->id,
            'amount' => $request->input('amount'),
            'email' => $email
        ];
        $initialize_payment = $paystack->initialize_payment($data);
        // dd($initialize_payment);
        return response()->json($initialize_payment);
    }

    public function handle_paystack_callback(Request $request)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'error' => true,
                'message' => 'No reference supplied',
            ]);
        }

        $paystack = new Paystack();
        $verify = $paystack->verify_transaction($reference);
        $verify = json_decode($verify, true);

        // dd($verify);
        if ($verify && isset($verify['status']) && $verify['status'] == true) {
            // Payment was successful, update DB, notify user etc.
            return response()->json([
                'error' => false,
                'message' => 'Payment verified successfully',
                'data' => $verify['data']
            ]);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'Payment verification failed'
            ]);
        }
    }
}
