<?php

use App\Http\Controllers\Admin\TransactionController;
use App\Models\Category;
use App\Models\City;
use App\Models\ComboProduct;
use App\Models\Order;
use App\Models\OrderCharges;
use App\Models\OrderItems;
use App\Models\Otps;
use App\Models\Parcel;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\SellerStore;
use App\Models\Slider;
use App\Models\Store;
use App\Models\ReturnRequest;
use App\Models\Transaction;
use App\Models\Updates;
use App\Models\User;
use App\Models\Zipcode;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\TranslationService;
use App\Services\ProductService;
use App\Services\ComboProductService;
use App\Services\StoreService;
use App\Services\MediaService;
use App\Services\SettingService;
use App\Services\CurrencyService;
use App\Services\OrderService;
use App\Services\WalletService;
// generate unique slug

function generateSlug($newName, $tableName = 'categories', $slugField = 'slug', $currentSlug = '', $currentName = '')
{

    $slug = Str::slug($newName);

    // If the name hasn't changed, use the existing slug
    if ($currentName !== '' && $currentName == $newName && $currentSlug !== '') {
        return $currentSlug;
    }


    $count = 0;
    $slugBase = $slug;

    // Check if the generated slug already exists in the database
    while (DB::table($tableName)->where($slugField, $slug)->exists()) {
        $count++;
        $slug = $slugBase . '-' . $count;
    }

    return $slug;
}


if (!function_exists('getCategoriesOptionHtml')) {
    function getCategoriesOptionHtml($categories, $selected_vals = [], $level = 0, &$all_subcategory_ids = [])
    {
        $html = '';

        // First pass: collect all subcategory IDs (only at the root level)
        if ($level === 0) {
            foreach ($categories as $category) {
                if (!empty($category['children'])) {
                    foreach ($category['children'] as $child) {
                        $all_subcategory_ids[] = $child['id'];
                    }
                }
            }
        }

        foreach ($categories as $category) {
            $categoryId = $category['id'];

            // Skip selected or root-level categories that are also subcategories
            if (in_array($categoryId, $selected_vals)) {
                continue;
            }
            if ($level === 0 && in_array($categoryId, $all_subcategory_ids)) {
                continue;
            }

            // Get translated category name
            $languageCode = app(TranslationService::class)->getLanguageCode();
            $categoryName = app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $categoryId, $languageCode);

            // Prepare HTML attributes
            $indentation = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            $style = $level > 0 ? 'style="color: gray;"' : '';

            $html .= sprintf(
                '<option value="%d" class="l%d" %s>%s%s</option>',
                $categoryId,
                $category['level'],
                $style,
                $indentation,
                e($categoryName)
            );

            // Recursively process children
            if (!empty($category['children'])) {
                $html .= getCategoriesOptionHtml($category['children'], $selected_vals, $level + 1, $all_subcategory_ids);
            }
        }

        return $html;
    }
}

function outputEscaping($array)
{
    $exclude_fields = ["images", "other_images"];
    $data = null;

    if (!empty($array)) {
        if (is_array($array)) {
            $data = [];
            foreach ($array as $key => $value) {
                if (!in_array($key, $exclude_fields)) {
                    $data[$key] = stripslashes((string) $value);
                } else {
                    $data[$key] = $value;
                }
            }
        } elseif (is_object($array)) {
            $data = new \stdClass();
            foreach ($array as $key => $value) {
                if (!in_array($key, $exclude_fields)) {
                    $data->$key = stripslashes($value);
                } else {
                    $data->$key = $value;
                }
            }
        } else {
            $data = stripslashes($array);
        }
    }

    return $data;
}

function isExist($where, $model, $update_id = null)
{
    // Validate model class
    if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
        throw new InvalidArgumentException("Invalid Eloquent model: $model");
    }

    // Initialize query from model
    $query = $model::query();

    // Apply where conditions
    foreach ($where as $key => $val) {
        $query->where($key, $val);
    }

    // Exclude a specific ID (usually for update)
    if ($update_id !== null) {
        $query->where('id', '!=', $update_id);
    }

    return $query->exists();
}

// use for fetch particular or whole details from table

function fetchDetails($model, $where = null, $fields = ['*'], $limit = null, $offset = null, $sort = null, $order = 'asc', $where_in_key = null, $where_in_value = null)
{
    // Validate model class
    if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
        throw new InvalidArgumentException("Invalid Eloquent model: $model");
    }

    // Start query
    $query = $model::select($fields);

    if (!empty($where)) {
        $query->where($where);
    }

    if (!empty($where_in_key) && !empty($where_in_value)) {
        $query->whereIn($where_in_key, $where_in_value);
    }

    if (!empty($sort) && !empty($order)) {
        $query->orderBy($sort, $order);
    }

    if (!empty($limit)) {
        $query->limit($limit);
    }

    if (!empty($offset)) {
        $query->offset($offset);
    }

    // return $query->get()->toArray();
    return $query->get();
}

function deleteDetails($where, $model)
{
    try {
        $model::where($where)->delete();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
function findDiscountInPercentage($special_price, $price)
{
    $diff_amount = $price - $special_price;
    if ($diff_amount > 0) {
        return intval(($diff_amount * 100) / $price);
    }
}
function subCategories($id, $level)
{
    $level = $level + 1;
    $category = Category::find($id);
    $categories = $category->children;

    $i = 0;
    foreach ($categories as $p_cat) {
        $categories[$i]->children = subCategories($p_cat->id, $level);
        $categories[$i]->text = e($p_cat->name); // Use the Laravel "e" helper for output escaping
        $categories[$i]->state = ['opened' => true];
        $categories[$i]->level = $level;
        $p_cat['image'] = app(MediaService::class)->getImageUrl($p_cat['image'], 'thumb', 'md');
        $p_cat['banner'] = app(MediaService::class)->getImageUrl($p_cat['banner'], 'thumb', 'md');
        $i++;
    }

    return $categories;
}
function validateStock($product_variant_ids, $qtns, $product_type = "")
{
    $is_exceed_allowed_quantity_limit = false;
    $errors = [];
    $response = [];

    foreach ($product_variant_ids as $index => $product_variant_id) {
        if ($product_type[$index] == 'regular') {
            $product_variant = Product_variants::with(['product:id,stock_type,stock,sku,availability'])
                ->where('id', $product_variant_id)
                ->first();
        } else {
            $product_variant = ComboProduct::where('id', $product_variant_id)->first();
        }

        if ($product_variant->total_allowed_quantity !== null && $product_variant->total_allowed_quantity > 0) {
            $total_allowed_quantity = intval($product_variant->total_allowed_quantity) - intval($qtns[$index]);
            if ($total_allowed_quantity < 0) {
                $is_exceed_allowed_quantity_limit = true;
                $errors[] = [
                    'product_variant_id' => $product_variant->id,
                    'message' => 'Quantity exceeds the allowed limit.'
                ];
                continue;
            }
        }

        if ($product_type[$index] == 'regular') {
            if (($product_variant->stock_type !== null || $product_variant->stock_type !== 'null') && $product_variant->stock_type !== '') {
                if ($product_variant->stock_type == 0) {
                    if ($product_variant->product->stock !== null && $product_variant->product->stock !== '') {
                        $stock = intval($product_variant->product->stock) - intval($qtns[$index]);
                        if ($stock < 0 || $product_variant->product->availability == 0) {
                            $errors[] = [
                                'product_variant_id' => $product_variant->id,
                                'message' => 'Product is out of stock.'
                            ];
                        }
                    }
                } elseif ($product_variant->stock_type == 1 || $product_variant->stock_type == 2) {
                    if ($product_variant->stock !== null && $product_variant->stock !== '') {
                        $stock = intval($product_variant->stock) - intval($qtns[$index]);
                        if ($stock < 0 || $product_variant->availability == 0) {
                            $errors[] = [
                                'product_variant_id' => $product_variant->id,
                                'message' => 'Product is out of stock.'
                            ];
                        }
                    }
                }
            }
        } else {
            if ($product_variant->stock !== null && $product_variant->stock !== '') {
                $stock = intval($product_variant->stock) - intval($qtns[$index]);
                if ($stock < 0 || $product_variant->availability == 0) {
                    $errors[] = [
                        'product_variant_id' => $product_variant->id,
                        'message' => 'Product is out of stock.'
                    ];
                }
            }
        }
    }

    if (!empty($errors)) {
        $response['error'] = true;
        $response['errors'] = $errors;
        if ($is_exceed_allowed_quantity_limit) {
            $response['message'] = 'Some products exceed the allowed quantity.';
        } else {
            $response['message'] = "Some products are out of stock.";
        }
    } else {
        $response['error'] = false;
        $response['message'] = "Stock available for purchasing.";
    }

    return $response;
}


function fetchUsers($id)
{
    $userDetails = User::select(
        'users.id',
        'users.username',
        'users.email',
        'users.mobile',
        'users.balance',
        'users.dob',
        'users.referral_code',
        'users.friends_code',
        'c.name as cities',
        'a.name as area',
        'users.street',
        'users.pincode'
    )
        ->leftJoin('areas as a', 'users.area', '=', 'a.name')
        ->leftJoin('cities as c', 'users.city', '=', 'c.name')
        ->where('users.id', $id)
        ->first();

    return $userDetails;
}

function updateDetails(array $set, array $where, string $modelClass)
{
    try {
        DB::beginTransaction();

        // Fetch the records based on where conditions
        $query = $modelClass::where($where);

        // Update the records
        $updated = $query->update($set);

        DB::commit();
        return $updated > 0;
    } catch (\Exception $e) {
        DB::rollBack();
        return false;
    }
}

function getSliders($id = '', $type = '', $type_id = '', $store_id = '')
{
    $query = Slider::query();

    if (!empty($id)) {
        $query->where('id', $id);
    }
    if (!empty($type)) {
        $query->where('type', $type);
    }
    if (!empty($type_id)) {
        $query->where('type_id', $type_id);
    }
    if (!empty($store_id)) {
        $query->where('store_id', $store_id);
    }

    $res = $query->get();
    $res = $res->map(function ($d) {
        if ($d->type === "default") {
            $d['link'] = '';
        }

        if (!empty($d->type)) {
            if ($d->type === "categories") {
                $typeDetails = Category::where('id', $d->type_id)->select('slug')->first();
                if (!empty($typeDetails)) {
                    $d['link'] = customUrl('categories/' . $typeDetails->slug . '/products');
                }
            } elseif ($d->type === "products") {
                $typeDetails = Product::where('id', $d->type_id)->select('slug')->first();
                if (!empty($typeDetails)) {
                    $d['link'] = customUrl('products/' . $typeDetails->slug);
                }
            } elseif ($d->type === "combo_products") {
                $typeDetails = ComboProduct::where('id', $d->type_id)->select('slug')->first();
                if (!empty($typeDetails)) {
                    $d['link'] = customUrl('combo-products/' . $typeDetails->slug);
                }
            }
        }

        $d['image'] = app(MediaService::class)->dynamic_image(app(MediaService::class)->getMediaImageUrl($d->image), 1920);

        return $d;
    });

    return $res;
}

function processReferralBonus($user_id, $order_id, $status)
{
    /*
        $user_id = 99;              << user ID of the person whose order is being marked not the friend's ID who is going to get the bonus
        $status = "delivered";      << current status of the order
        $order_id = 644;            << Order which is being marked as delivered

    */
    $settings = app(SettingService::class)->getSettings('system_settings', true);
    $settings = json_decode($settings, true);
    if (isset($settings['refer_and_earn_status']) && $settings['refer_and_earn_status'] == 1 && $status == "delivered") {
        $user = fetchUsers($user_id);

        /* check if user has set friends code or not */
        if (isset($user[0]->friends_code) && !empty($user[0]->friends_code)) {

            /* find number of previous orders of the user */
            $total_orders = fetchDetails(Order::class, ['user_id' => $user_id], 'COUNT(id) as total');
            $total_orders = $total_orders[0]->total;

            if ($total_orders < $settings['number_of_times_bonus_given_to_customer']) {

                /* find a friends account details */
                $friend_user = fetchDetails(User::class, ['referral_code' => $user[0]->friends_code], ['id', 'username', 'email', 'mobile', 'balance']);
                if (!$friend_user->isEmpty()) {
                    $order = app(OrderService::class)->fetchOrders($order_id);
                    $final_total = $order['order_data'][0]->final_total;
                    if ($final_total >= $settings['minimum_refer_and_earn_amount']) {
                        $referral_bonus = 0;
                        if ($settings['refer_and_earn_method'] == 'percentage') {
                            $referral_bonus = $final_total * ($settings['minimum_refer_and_earn_bonus'] / 100);
                            if ($referral_bonus > $settings['max_refer_and_earn_amount']) {
                                $referral_bonus = $settings['max_refer_and_earn_amount'];
                            }
                        } else {
                            $referral_bonus = $settings['minimum_refer_and_earn_bonus'];
                        }

                        $referral_id = "refer-and-earn-" . $order_id;
                        $previous_referral = fetchDetails(Transaction::class, ['order_id' => $referral_id], ['id', 'amount']);
                        if ($previous_referral->isEmpty()) {

                            $transaction_data = new Request([
                                'transaction_type' => "wallet",
                                'user_id' => $friend_user[0]->id,
                                'order_id' => $referral_id,
                                'type' => "credit",
                                'txn_id' => "",
                                'amount' => $referral_bonus,
                                'status' => "success",
                                'message' => "Refer and Earn bonus on " . $user[0]->username . "'s order",
                            ]);
                            $transactionController = app(TransactionController::class);
                            $transactionController->store($transaction_data);

                            if (app(WalletService::class)->updateBalance($referral_bonus, $friend_user[0]['id'], 'add')) {
                                $response['error'] = false;
                                $response['message'] = "User's wallet credited successfully";
                                return $response;
                            }
                        } else {
                            $response['error'] = true;
                            $response['message'] = "Bonus is already given for the following order!";
                            return $response;
                        }
                    } else {
                        $response['error'] = true;
                        $response['message'] = "This order amount is not eligible refer and earn bonus!";
                        return $response;
                    }
                } else {
                    $response['error'] = true;
                    $response['message'] = "Friend user not found for the used referral code!";
                    return $response;
                }
            } else {
                $response['error'] = true;
                $response['message'] = "Number of orders have exceeded the eligible first few orders!";
                return $response;
            }
        } else {
            $response['error'] = true;
            $response['message'] = "No friends code found!";
            return $response;
        }
    } else {
        if ($status == "delivered") {
            $response['error'] = true;
            $response['message'] = "Referred and earn system is turned off";
            return $response;
        } else {
            $response['error'] = true;
            $response['message'] = "Status must be set to delivered to get the bonus";
            return $response;
        }
    }
}

function countNewUsers()
{
    $roleId = 2;

    $startOfCurrentMonth = Carbon::now()->startOfMonth();
    $endOfCurrentMonth = Carbon::now()->endOfMonth();

    $startOfPreviousMonth = Carbon::now()->subMonth()->startOfMonth();
    $endOfPreviousMonth = Carbon::now()->subMonth()->endOfMonth();

    // Total users with role_id = 2
    $totalUsers = User::where('role_id', $roleId)->count();

    // Current month users
    $currentMonthUsers = User::where('role_id', $roleId)
        ->whereBetween('created_at', [$startOfCurrentMonth, $endOfCurrentMonth])
        ->count();

    // Previous month users
    $previousMonthUsers = User::where('role_id', $roleId)
        ->whereBetween('created_at', [$startOfPreviousMonth, $endOfPreviousMonth])
        ->count();

    // Active users
    $activeUser = User::where('role_id', $roleId)
        ->where('active', 1)
        ->count();

    // Inactive users (either 0 or null)
    $inactiveUser = User::where('role_id', $roleId)
        ->where(function ($query) {
            $query->where('active', 0)
                ->orWhereNull('active');
        })->count();

    // Percentage change calculation
    $percentageChange = 0;
    if ($previousMonthUsers > 0) {
        $percentageChange = (($currentMonthUsers - $previousMonthUsers) / $previousMonthUsers) * 100;
    }

    return [
        'total_users' => $totalUsers,
        'current_month_users' => $currentMonthUsers,
        'previous_month_users' => $previousMonthUsers,
        'percentage_change' => round($percentageChange, 2),
        'active_user' => $activeUser,
        'inactive_user' => $inactiveUser,
    ];
}

function countDeliveryBoys()
{
    $counter = User::where('role_id', 3)
        ->count();

    return $counter;
}
function getTransactions($id = '', $user_id = '', $transaction_type = '', $type = '', $search = '', $offset = 0, $limit = 25, $sort = 'id', $order = 'DESC')
{
    $query = Transaction::query();

    // Basic where conditions
    if (!empty($id)) {
        $query->where('id', $id);
    }
    if (!empty($user_id)) {
        $query->where('user_id', $user_id);
    }
    if (!empty($transaction_type)) {
        $query->where('transaction_type', $transaction_type);
    }
    if (!empty($type)) {
        $query->where('type', $type);
    }

    // Search conditions
    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->orWhere('id', 'LIKE', "%$search%")
                ->orWhere('transaction_type', 'LIKE', "%$search%")
                ->orWhere('type', 'LIKE', "%$search%");
        });
    }

    // Clone the query to count total before pagination
    $total = (clone $query)->count();

    // Apply ordering and pagination
    $transactions = $query->orderBy($sort, $order)
        ->offset($offset)
        ->limit($limit)
        ->get()
        ->map(function ($item) {
            $item->message = str_replace(["\n", "\r"], '', $item->message);

            foreach ($item->getAttributes() as $key => $value) {
                $item->$key = $value === null ? '' : $value;
            }

            return $item;
        });

    return [
        'data' => $transactions,
        'total' => $total,
    ];
}

function countProductsStockLowStatus($seller_id = "", $store_id = "")
{
    // Get low stock threshold from system settings
    $settings = json_decode(app(SettingService::class)->getSettings('system_settings', true), true);
    $lowStockLimit = $settings['low_stock_limit'] ?? 5;

    // Query for regular products
    $regularProductCount = Product::query()
        ->whereNotNull('stock_type')
        ->where(function ($query) use ($lowStockLimit) {
            $query->where(function ($q) use ($lowStockLimit) {
                $q->where('stock', '<=', $lowStockLimit)
                    ->where('availability', '1');
            })->orWhereHas('variants', function ($q) use ($lowStockLimit) {
                $q->where('stock', '<=', $lowStockLimit)
                    ->where('availability', '1');
            });
        })
        ->when($seller_id, fn($q) => $q->where('seller_id', $seller_id))
        ->when($store_id, fn($q) => $q->where('store_id', $store_id))
        ->distinct('id')
        ->count('id');

    // Query for combo products
    $comboProductCount = ComboProduct::query()
        ->where('availability', '1')
        ->whereRaw('CAST(stock AS SIGNED) <= ?', [$lowStockLimit])
        ->when($seller_id, fn($q) => $q->where('seller_id', $seller_id))
        ->when($store_id, fn($q) => $q->where('store_id', $store_id))
        ->distinct('id')
        ->count('id');

    return $regularProductCount + $comboProductCount;
}
function count_new_user()
{
    return User::where('role_id', 2)->count();
}

function getDeliveryBoys($id, $search, $offset, $limit, $sort, $order, $seller_city = '', $seller_zipcode = '', $store_deliverability_type = '', $seller_zone_ids = [], $deliverable_type = "")
{
    $query = User::with('city')
        ->where('role_id', 3);

    // Filter by specific delivery boy ID
    if (!empty($id)) {
        $query->where('id', $id);
    }

    // Apply search filters
    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('id', 'like', "%$search%")
                ->orWhere('username', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('mobile', 'like', "%$search%")
                ->orWhereHas('city', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%");
                });
        });
    }

    // Filter by seller zone IDs (if deliverable_type != 1)
    if ($deliverable_type != 1 && !empty($seller_zone_ids)) {
        $query->where(function ($q) use ($seller_zone_ids) {
            foreach ($seller_zone_ids as $zone_id) {
                $q->orWhereRaw("FIND_IN_SET(?, serviceable_zones)", [$zone_id]);
            }
        });
    }

    // Get total matching records
    $total = $query->count();

    // Apply sorting, pagination, and fetch records
    $deliveryBoys = $query->orderBy($sort, $order)
        ->skip($offset)
        ->take($limit)
        ->get();

    // Format the output
    $data = $deliveryBoys->map(function ($user) {
        return [
            'id' => $user->id,
            'name' => str_replace("\r\n", '', $user->username),
            'mobile' => $user->mobile,
            'email' => $user->email,
            'balance' => $user->balance,
            'city' => $user->city->name ?? '',
            'image' => app(MediaService::class)->getMediaImageUrl($user->image, 'DELIVERY_BOY_IMG_PATH'),
            'street' => $user->street,
            'status' => $user->active,
            'date' => Carbon::parse($user->created_at)->format('d-m-Y'),
        ];
    });

    return [
        'error' => $data->isEmpty(),
        'message' => $data->isEmpty() ? 'Delivery(s) does not exist' : 'Delivery boys retrieved successfully',
        'total' => $total,
        'data' => $data,
    ];
}

function validateOtp($otp, $orderItemId = null, $orderId = null, $sellerId = null, $parcel_id = '')
{

    $orderItem = OrderItems::where('id', $orderItemId)->first(['otp']);
    $parcel_details = Parcel::where('id', $parcel_id)->get();

    $orderCharge = OrderCharges::where('order_id', $orderId)
        ->where('seller_id', $sellerId)
        ->first(['otp']);
    if (
        ($orderItem && $orderItem->otp != 0 && $orderItem->otp == $otp) ||
        ($orderCharge && $orderCharge->otp != 0 && $orderCharge->otp == $otp) || ($parcel_details[0]->otp != 0 && $parcel_details[0]->otp == $otp)
    ) {
        return true;
    } else {
        return false;
    }
}



function getAuthenticatedUser()
{
    // Check the 'web' guard (users)
    if (Auth::guard('web')->check()) {
        return Auth::guard('web')->user();
    }
    // No user is authenticated
    return null;
}

function createRow($product, $variant, $category_name, $from_seller = '')
{

    $action = '<div class="d-flex align-items-center ">
                <a href="#" class="btn edit-seller-stock single_action_button" title="Edit" data-id="' . $variant['id'] . '" data-bs-toggle="modal" data-bs-target="#edit_modal">
                    <i class="bx bx-pencil mx-2"></i>
                </a>
                </div>';


    if ($product['stock_type'] == 0 || $product['stock_type'] == null) {

        $stock_status = $product['availability'] == 1 ? '<label class="badge bg-success">In Stock</label>' : '<label class="badge bg-danger">Out of Stock</label>';
    } else {
        $stock_status = $variant['availability'] == 1 ? '<label class="badge bg-success">In Stock</label>' : '<label class="badge bg-danger">Out of Stock</label>';
    }
    $price = isset($variant['special_price']) && $variant['special_price'] > 0 ? $variant['special_price'] : $variant['price'];
    $route_name = isset($from_seller) && $from_seller == '1' ? 'seller.dynamic_image' : 'admin.dynamic_image';
    $language_code = app(TranslationService::class)->getLanguageCode();
    // Define the image parameters
    $image_params = [
        'url' => $product['image'],
        'width' => 60,
        'quality' => 90
    ];
    // dd($variant['variant_values']);
    // Generate the product image URL using the determined route name
    $product_image = route($route_name, $image_params);
    $productName = app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product['id'], $language_code);

    // Only wrap in () if not empty
    $variantValues = !empty($variant['variant_values'])
        ? '(' . $variant['variant_values'] . ')'
        : '';

    $tempRow = [
        'id' => $variant['id'],
        'category_name' => app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category_name[0]->id, $language_code),
        'price' => app(CurrencyService::class)->formateCurrency(formatePriceDecimal($price)),
        'stock_count' => $product['stock_type'] == 0 ? $product['stock'] : $variant['stock'],
        'stock_status' => $stock_status,
        'name' => '<div class="d-flex align-items-center">
        <a href="' . app(MediaService::class)->getMediaImageUrl($product_image) . '" data-lightbox="image-' . $variant['id'] . '">
            <img src="' . $product_image . '" class="rounded mx-2">
        </a>
        <div class="ms-2">
            <p class="m-0">' . $productName . '</p>
            ' . (!empty($variantValues) ? '<p>' . $variantValues . '</p>' : '') . '
        </div>
    </div>',
        'operate' => $action
    ];

    return $tempRow;
}


function formatePriceDecimal($price)
{
    return number_format($price, 2);
}

if (!function_exists('renderCategories')) {
    function renderCategories($categories, $parent_id = 0, $depth = 0, $selected_id = null)
    {
        $language_code = app(TranslationService::class)->getLanguageCode();
        $html = '';
        foreach ($categories as $category) {
            if ($category['parent_id'] == $parent_id) {
                $is_selected = ($category['id'] == $selected_id) ? 'selected' : '';
                $bold_style = ($depth == 0) ? 'font-weight: bold;' : '';
                $padding = str_repeat('&nbsp;', $depth * 4);
                $prefix = str_repeat('-', $depth); // use this if want to add - in left side of sub catgory name
                $html .= sprintf(
                    '<option value="%s" %s style="padding-left: %spx; %s">%s%s</option>',
                    htmlspecialchars($category['id']),
                    $is_selected,
                    $depth * 20,
                    $bold_style,
                    $padding,
                    htmlspecialchars(app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $category['id'], $language_code))
                );
                $html .= renderCategories($categories, $category['id'], $depth + 1, $selected_id);
            }
        }
        return $html;
    }
}

function labels($path, $label)
{
    return !trans()->has($path) ? $label : trans($path);
}

function isForeignKeyInUse($models, $columns, $id, $is_comma_seprated_values = 0)
{
    // Ensure $models is an array
    $models = is_array($models) ? $models : [$models];

    // Loop through models
    foreach ($models as $model) {
        // Check if class exists and is an Eloquent model
        if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
            continue;
        }

        $instance = new $model;

        if ($is_comma_seprated_values == 1) {
            // Single column expected when comma separated
            if (is_string($columns)) {
                if ($instance->whereRaw("FIND_IN_SET(?, {$columns})", [$id])->exists()) {
                    return true;
                }
            }
            continue;
        }

        // Support for single or multiple columns
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            if (in_array($column, Schema::getColumnListing($instance->getTable()))) {
                if ($column === 'category_ids') {
                    if ($instance->whereRaw("FIND_IN_SET(?, {$column})", [$id])->exists()) {
                        return true;
                    }
                } else {
                    if ($instance->where($column, $id)->exists()) {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}
function AdmintotalEarnings()
{
    $store_id = app(StoreService::class)->getStoreId();
    $select = DB::raw('SUM(sub_total) as total');

    $total = OrderItems::select($select)
        ->where('store_id', $store_id)
        ->get()
        ->first();

    $formattedTotal = number_format($total->total ?? 0, 2, '.', ',');

    return $formattedTotal;
}

function getFavorites($user_id, $limit = 10, $offset = 0, $store_id = null, $select = "*")
{
    $productQuery = Product::whereHas('favorites', function ($q) use ($user_id) {
        $q->where('user_id', $user_id);
    });

    $comboQuery = ComboProduct::whereHas('favorites', function ($q) use ($user_id) {
        $q->where('user_id', $user_id);
    });

    if ($store_id !== null) {
        $productQuery->where('store_id', $store_id);
        $comboQuery->where('store_id', $store_id);
    }

    $productCount = (clone $productQuery)->count();
    $comboCount = (clone $comboQuery)->count();

    $products = $productQuery->orderByDesc('id')->skip($offset)->take($limit)->get();
    $comboProducts = $comboQuery->orderByDesc('id')->skip($offset)->take($limit)->get();

    $formatProduct = function ($product) {
        // dd($product);
        return [
            'id' => $product->id,
            'name' => $product->name ?? '',
            'slug' => $product->slug ?? '',
            'type' => $product->type ?? '',
            'brand' => $product->brand ?? '',
            'rating' => $product->rating ?? '',
            'total_allowed_quantity' => $product->total_allowed_quantity ?? '',
            'quantity_step_size' => $product->quantity_step_size ?? '',
            'minimum_order_quantity' => $product->minimum_order_quantity ?? '',
            'store_id' => $product->store_id ?? '',
            'stock_type' => $product->stock_type ?? '',
            'image' => app(MediaService::class)->getImageUrl($product->image),
            'image_md' => app(MediaService::class)->getImageUrl($product->image, 'thumb', 'md'),
            'image_sm' => app(MediaService::class)->getImageUrl($product->image, 'thumb', 'sm'),
            'variants' => app(ProductService::class)->getVariantsValuesByPid($product->id),
            'min_max_price' => app(ProductService::class)->getMinMaxPriceOfProduct($product->id),
        ];
    };
    $formatComboProduct = function ($product) {

        return [
            'id' => $product->id,
            'name' => $product->name ?? '',
            'slug' => $product->slug ?? '',
            'type' => $product->type ?? '',
            'rating' => $product->rating ?? '',
            'total_allowed_quantity' => $product->total_allowed_quantity ?? '',
            'quantity_step_size' => $product->quantity_step_size ?? '',
            'minimum_order_quantity' => $product->minimum_order_quantity ?? '',
            'price' => $product->price ?? '',
            'special_price' => $product->special_price ?? '',
            'image' => app(MediaService::class)->getImageUrl($product->image),
            'image_md' => app(MediaService::class)->getImageUrl($product->image, 'thumb', 'md'),
            'image_sm' => app(MediaService::class)->getImageUrl($product->image, 'thumb', 'sm'),

        ];
    };

    return [
        'regular_product' => $products->map($formatProduct),
        'combo_products' => $comboProducts->map($formatComboProduct),
        'favorites_count' => $productCount + $comboCount,
    ];
}



function getPreviousAndNextItemWithId($model, $id, $storeId)
{
    // Fetch previous item: id less than current, ordered descending to get closest smaller id
    $previous_product = $model::where('id', '<', $id)
        ->where('store_id', $storeId)
        ->orderBy('id', 'desc')
        ->first();

    // Fetch next item: id greater than current, ordered ascending to get closest larger id
    $next_product = $model::where('id', '>', $id)
        ->where('store_id', $storeId)
        ->orderBy('id', 'asc')
        ->first();

    $next_id = $next_product->id ?? null;
    $previous_id = $previous_product->id ?? null;

    // Determine fetch function based on model class
    if ($model == ComboProduct::class) {
        $products = app(ComboProductService::class)->fetchComboProduct(id: [$next_id, $previous_id]);
        $next_product = $products['combo_product'][0] ?? "";
        $previous_product = $products['combo_product'][1] ?? "";
    } else {
        $products = app(ProductService::class)->fetchProduct(null, null, [$next_id, $previous_id]);
        $next_product = $products['product'][0] ?? "";
        $previous_product = $products['product'][1] ?? "";
    }

    return [
        'next_product' => $next_product,
        'previous_product' => $previous_product,
    ];
}

// function for check isset and not empty
function isKeySetAndNotEmpty($array, $key)
{
    return isset($array[$key]) && !empty($array[$key]);
}

function calculatePriceWithTax($percentage, $price)
{
    $tax_percentage = explode(',', ($percentage));
    // $total_tax = array_sum($tax_percentage); add floatval becuse showing 500 in web
    $total_tax = array_sum(array_map('floatval', $tax_percentage));
    $price_tax_amount = $price * ($total_tax / 100);
    $price_with_tax_amount = $price + $price_tax_amount;

    return $price_with_tax_amount;
}

// sms gateway

function parse_sms(string $string = "", string $mobile = "", string $sms = "", string $country_code = "")
{
    $parsedString = str_replace("{only_mobile_number}", $mobile, $string);
    $parsedString = str_replace("{message}", $sms, $parsedString); // Use $parsedString as the third argument

    return $parsedString;
}
function set_user_otp($mobile, $otp)
{
    $dateString = date('Y-m-d H:i:s');
    $time = strtotime($dateString);

    $otps = fetchDetails(Otps::class, ['mobile' => $mobile]);
    $data['otp'] = $otp;
    $data['created_at'] = $time;

    foreach ($otps as $user) {
        if (isset($user->mobile) && !empty($user->mobile)) {
            send_sms($mobile, "please don't share with anyone $otp");
            Otps::where('id', $user->id)->update($data); // Updated to use the $data array

            return [
                "error" => false,
                "message" => "OTP sent successfully.",
                "data" => $data
            ];
        }
    }

    // If no user is found or an error occurs
    return [
        "error" => true,
        "message" => "Something went wrong."
    ];
}



// function checkOTPExpiration($otpTime)
// {

//     $time = date('Y-m-d H:i:s');
//     $currentTime = strtotime($time);
//     $timeDifference = $currentTime - $otpTime;


//     if ($timeDifference <= 60) {
//         return [
//             "error" => false,
//             "message" => "Success: OTP is valid."
//         ];
//     } else {
//         return [
//             "error" => true,
//             "message" => "OTP has expired."
//         ];
//     }
// }

function checkOTPExpiration($otpTime)
{
    // Ensure $otpTime is a Carbon instance
    if (!$otpTime instanceof Carbon) {
        $otpTime = Carbon::parse($otpTime);
    }

    $currentTime = Carbon::now();
    $timeDifference = $currentTime->diffInSeconds($otpTime);

    if ($currentTime->lte($otpTime->copy()->addSeconds(60))) {
        return [
            "error" => false,
            "message" => "Success: OTP is valid."
        ];
    } else {
        return [
            "error" => true,
            "message" => "OTP has expired."
        ];
    }
}


function setUrlParameter($url, $paramName, $paramValue)
{
    $paramName = str_replace(' ', '-', $paramName);
    if ($paramValue == null || $paramValue == '') {
        return preg_replace('/[?&]' . preg_quote($paramName) . '=[^&#]*(#.*)?$/', '$1', $url);
    }
    $pattern = '/\b(' . preg_quote($paramName) . '=).*?(&|#|$)/';
    if (preg_match($pattern, $url)) {
        return preg_replace($pattern, '$1' . $paramValue . '$2', $url);
    }
    $url = preg_replace('/[?#]$/', '', $url);
    return $url . (strpos($url, '?') > 0 ? '&' : '?') . $paramName . '=' . $paramValue;
}


function customUrl($name)
{
    $store = session()->get('store_slug');
    if (Route::has($name)) {
        return route($name, ['store' => $store]);
    }
    $url = setUrlParameter($name, 'store', $store);

    return url($url);
}

if (!function_exists('get_system_update_info')) {
    function get_system_update_info()
    {
        $updatePath = Config::get('constants.UPDATE_PATH');
        $updaterPath = $updatePath . 'updater.json';
        $subDirectory = (File::exists($updaterPath) && File::exists($updatePath . 'update/updater.json')) ? 'update/' : '';

        if (File::exists($updaterPath) || File::exists($updatePath . $subDirectory . 'updater.json')) {
            $updaterFilePath = File::exists($updaterPath) ? $updaterPath : $updatePath . $subDirectory . 'updater.json';
            $updaterContents = File::get($updaterFilePath);

            // Check if the file contains valid JSON data
            if (!json_decode($updaterContents)) {
                throw new \RuntimeException('Invalid JSON content in updater.json');
            }

            $linesArray = json_decode($updaterContents, true);

            if (!isset($linesArray['version'], $linesArray['previous'], $linesArray['manual_queries'], $linesArray['query_path'])) {
                throw new \RuntimeException('Invalid JSON structure in updater.json');
            }
        } else {
            throw new \RuntimeException('updater.json does not exist');
        }

        $dbCurrentVersion = Updates::latestById()->first();

        $data['db_current_version'] = $dbCurrentVersion ? $dbCurrentVersion->version : '0.0.0';
        // if ($data['db_current_version'] == $linesArray['version']) {
        //     $data['updated_error'] = true;
        //     $data['message'] = 'Oops!. This version is already updated into your system. Try another one.';
        //     return $data;
        // }
        if ($data['db_current_version'] == $linesArray['previous']) {
            $data['file_current_version'] = $linesArray['version'];
        } else {
            $data['sequence_error'] = true;
            $data['message'] = 'Oops!. Update must performed in sequence.';
            return $data;
        }

        $data['query'] = $linesArray['manual_queries'];
        $data['query_path'] = $linesArray['query_path'];

        return $data;
    }
}

if (!function_exists('get_current_version')) {

    function get_current_version()
    {
        $dbCurrentVersion = Updates::latestById()->first();
        return $dbCurrentVersion ? $dbCurrentVersion->version : '1.0.0';
    }
}

function getCityNamesFromIds($cityIds, $language_code = '')
{
    $cityIdsArray = explode(',', $cityIds);

    $cities = City::whereIn('id', $cityIdsArray)->get();

    $translated_names = [];

    foreach ($cities as $city) {
        if ($language_code) {
            $translated_name = app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $city->id, $language_code);
        } else {
            $translated_name = $city->name;
        }

        $translated_names[] = $translated_name;
    }

    return $translated_names;
}


function getZipcodesFromIds($zipcodeIds)
{
    $zipcodeIdsArray = explode(',', $zipcodeIds);

    // Fetch zipcodes from the database
    return Zipcode::whereIn('id', $zipcodeIdsArray)->pluck('zipcode')->toArray();
}
function getZones($request, $language_code = '')
{
    // dd($request);
    $validator = Validator::make($request->all(), [
        'limit' => 'numeric',
        'offset' => 'numeric',
        'search' => 'string|nullable',
        'seller_id' => 'numeric|nullable',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => true,
            'message' => $validator->errors()->all(),
            'code' => 102,
        ]);
    }

    $order = $request->input('order', 'DESC');
    $sort = $request->input('sort', 'id');
    $limit = $request->input('limit', 25);
    $offset = $request->input('offset', 0);
    $search = trim($request->input('search'));
    $seller_id = $request->input('seller_id');
    $language_code = isset($language_code) && !empty($language_code) ? $language_code : $request->input('language_code');
    // dd($language_code);
    // Base query
    $query = Zone::where('status', 1);

    if ($seller_id) {
        $seller = SellerStore::
            where('ss.seller_id', $seller_id)
            ->select('ss.deliverable_type', 'ss.deliverable_zones', 'ss.seller_id')
            ->first();
        if ($seller && !empty($seller->deliverable_zones)) {
            $zone_ids = is_string($seller->deliverable_zones) ? explode(',', $seller->deliverable_zones) : $seller->deliverable_zones;
            $query->whereIn('id', $zone_ids);
        } else {
            return response()->json([
                'error' => true,
                'message' => 'No deliverable zones found for this seller',
                'language_message_key' => 'seller_zones_not_found',
                'total' => 0,
                'data' => [],
            ]);
        }
    }

    // Search filter
    if ($search) {
        $query->where('name', 'like', '%' . $search . '%');
    }

    $total = $query->count();
    $zones = $query->orderBy($sort, $order)
        ->offset($offset)
        ->limit($limit)
        ->get();

    $city_ids = [];
    $zipcode_ids = [];

    foreach ($zones as $zone) {
        $ids = is_string($zone->serviceable_city_ids) ? explode(',', $zone->serviceable_city_ids) : $zone->serviceable_city_ids;
        $city_ids = array_merge($city_ids, $ids);

        $ids = is_string($zone->serviceable_zipcode_ids) ? explode(',', $zone->serviceable_zipcode_ids) : $zone->serviceable_zipcode_ids;
        $zipcode_ids = array_merge($zipcode_ids, $ids);
    }

    $city_ids = array_unique($city_ids);
    $zipcode_ids = array_unique($zipcode_ids);

    $cities = City::whereIn('id', $city_ids)->get()->keyBy('id');
    $zipcodes = Zipcode::whereIn('id', $zipcode_ids)->get()->keyBy('id');

    $response_data = $zones->map(function ($zone) use ($cities, $zipcodes, $language_code) {
        $city_ids = is_string($zone->serviceable_city_ids) ? explode(',', $zone->serviceable_city_ids) : $zone->serviceable_city_ids;
        $zipcode_ids = is_string($zone->serviceable_zipcode_ids) ? explode(',', $zone->serviceable_zipcode_ids) : $zone->serviceable_zipcode_ids;

        return [
            'zone_id' => $zone->id,
            'zone_name' => app(TranslationService::class)->getDynamicTranslation(Zone::class, 'name', $zone->id, $language_code),
            'cities' => collect($city_ids)->map(function ($city_id) use ($cities, $language_code) {
                $city = $cities->get($city_id);
                return $city ? [
                    'city_id' => $city->id,
                    'city_name' => app(TranslationService::class)->getDynamicTranslation(City::class, 'name', $city->id, $language_code),
                    'delivery_charges' => $city->delivery_charges,
                ] : null;
            })->filter(),
            'zipcodes' => collect($zipcode_ids)->map(function ($zipcode_id) use ($zipcodes) {
                $zipcode = $zipcodes->get($zipcode_id);
                return $zipcode ? [
                    'zipcode_id' => $zipcode->id,
                    'zipcode' => $zipcode->zipcode,
                    'delivery_charges' => $zipcode->delivery_charges,
                ] : null;
            })->filter(),
        ];
    });

    return response()->json([
        'error' => $response_data->isEmpty(),
        'message' => $response_data->isEmpty() ? 'Zones not found' : 'Zones retrieved successfully',
        'language_message_key' => $response_data->isEmpty() ? 'zones_not_found' : 'zones_retrieved_successfully',
        'total' => $total,
        'data' => $response_data,
    ]);
}

function calculatePrice($totalDiscountPercentage, $price)
{
    return $totalDiscountPercentage > 0 ? ($totalDiscountPercentage * $price) / 100 : $price;
}
function curl($url, $method = 'GET', $data = [], $authorization = "")
{
    $ch = curl_init();
    $curl_options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_HEADER => 0,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded',
        )
    );

    if (!empty($authorization)) {
        $curl_options['CURLOPT_HTTPHEADER'][] = $authorization;
    }

    if (strtolower($method) == 'post') {
        $curl_options[CURLOPT_POST] = 1;
        $curl_options[CURLOPT_POSTFIELDS] = http_build_query($data);
    } else {
        $curl_options[CURLOPT_CUSTOMREQUEST] = 'GET';
    }
    curl_setopt_array($ch, $curl_options);

    $result = array(
        'body' => json_decode(curl_exec($ch), true),
        'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    );
    return $result;
}
if (!function_exists('getProductDisplayComponent')) {
    function getProductDisplayComponent($store_settings)
    {
        $style = $store_settings['products_display_style_for_web'] ?? 'products_display_style_for_web_1';

        return match ($style) {
            'products_display_style_for_web_2' => 'utility.cards.productCardTwo',
            'products_display_style_for_web_3' => 'utility.cards.productCardThree',
            'products_display_style_for_web_4' => 'utility.cards.productCardFour',
            'products_display_style_for_web_5' => 'utility.cards.productCardFive',
            default => 'utility.cards.productCardOne',
        };
    }
}
if (!function_exists('getCategoryDisplayComponent')) {
    function getCategoryDisplayComponent($store_settings)
    {
        $style = $store_settings['categories_display_style_for_web'] ?? 'categories_display_style_for_web_1';

        return match ($style) {
            'categories_display_style_for_web_2' => 'utility.categories.cards.listCardTwo',
            'categories_display_style_for_web_3' => 'utility.categories.cards.listCardThree',
            default => 'utility.categories.cards.listCardOne',
        };
    }
}
if (!function_exists('getWishlistDisplayComponent')) {
    function getWishlistDisplayComponent($store_settings)
    {
        $style = $store_settings['wishlist_display_style_for_web'] ?? 'wishlist_display_style_for_web_1';

        return match ($style) {
            'wishlist_display_style_for_web_2' => 'utility.wishlist.cards.listCardTwo',
            default => 'utility.wishlist.cards.listCardOne',
        };
    }
}
if (!function_exists('getBrandDisplayComponent')) {
    function getBrandDisplayComponent($store_settings)
    {
        $style = $store_settings['brands_display_style_for_web'] ?? 'brands_display_style_for_web_1';

        return match ($style) {
            'brands_display_style_for_web_2' => 'utility.brands.cards.listCardTwo',
            'brands_display_style_for_web_3' => 'utility.brands.cards.listCardThree',
            default => 'utility.brands.cards.listCardTwo',
        };
    }
}
if (!function_exists('getHomeTheme')) {
    function getHomeTheme($store_settings)
    {
        $home_theme = $store_settings['web_home_page_theme'] ?? 'web_home_page_theme_1';

        return match ($home_theme) {
            'web_home_page_theme_2' => 'livewire.' . config('constants.theme') . '.home.homeThemeTwo',
            'web_home_page_theme_3' => 'livewire.' . config('constants.theme') . '.home.homeThemeThree',
            'web_home_page_theme_4' => 'livewire.' . config('constants.theme') . '.home.homeThemeFour',
            'web_home_page_theme_5' => 'livewire.' . config('constants.theme') . '.home.homeThemeFive',
            'web_home_page_theme_6' => 'livewire.' . config('constants.theme') . '.home.homeThemeSix',
            default => 'livewire.' . config('constants.theme') . '.home.home',
        };
    }
}
if (!function_exists('getHeaderStyle')) {
    function getHeaderStyle($store_settings)
    {
        $home_theme = $store_settings['web_home_page_theme'] ?? 'web_home_page_theme_1';

        return match ($home_theme) {
            'web_home_page_theme_2' => 'components.header.headerThemeTwo',
            'web_home_page_theme_3' => 'components.header.headerThemeThree',
            'web_home_page_theme_4' => 'components.header.headerThemeFour',
            'web_home_page_theme_5' => 'components.header.headerThemeFive',
            'web_home_page_theme_6' => 'components.header.headerThemeSix',
            default => 'components.header.header',
        };
    }
}
if (!function_exists('getProductDetailsStyle')) {
    function getProductDetailsStyle($store_settings)
    {
        $home_theme = $store_settings['web_product_details_style'] ?? 'web_product_details_style_1';

        return match ($home_theme) {
            'web_product_details_style_2' => 'livewire.' . config('constants.theme') . '.products.detailsStyleTwo',
            default => 'livewire.' . config('constants.theme') . '.products.details',
        };
    }
}
// function getReturnRequest($limit = 10, $offset = 0, $sort = 'id', $order = 'desc', $search = '', $seller_id = '')
// {
//     $limit = request()->input('limit', 10);
//     $offset = request()->input('offset', 0);
//     $sort = request()->input('sort', 'id');
//     $order = request()->input('order', 'ASC');
//     $search = trim(request()->input('search', ''));


//     $query = ReturnRequest::with(['user', 'product', 'orderItem.store'])
//         ->whereHas('orderItem', function ($q) use ($seller_id) {
//             $q->where('seller_id', $seller_id);
//         });

//     if (!empty($search)) {
//         $query->whereHas('user', fn($q) => $q->where('username', 'like', "%$search%"))
//             ->orWhereHas('product', fn($q) => $q->where('name', 'like', "%$search%"))
//             ->orWhereHas('orderItem.store', fn($q) => $q->where('name', 'like', "%$search%"))
//             ->orWhere('id', 'like', "%$search%")
//             ->orWhereHas('orderItem', fn($q) => $q->where('order_id', 'like', "%$search%"));
//     }

//     $total = $query->count();

//     $returnRequests = $query->orderBy($sort, $order)
//         ->skip($offset)
//         ->take($limit)
//         ->get()->toArray();

//     for ($i = 0; $i < count($returnRequests); $i++) {
//         unset($returnRequests[$i]['user']);
//         // unset($returnRequests[$i]['product']);
//         // unset($returnRequests[$i]['order_item']);
//     }

//     $data = [
//         'total' => $total,
//         'data' => $returnRequests
//     ];

//     return $data;

// }

function getReturnRequest($limit = 10, $offset = 0, $sort = 'id', $order = 'desc', $search = '', $seller_id = '', $languageCode = '')
{
    $limit = request()->input('limit', 10);
    $offset = request()->input('offset', 0);
    $sort = request()->input('sort', 'id');
    $order = request()->input('order', 'DESC');
    $search = trim(request()->input('search', ''));

    $query = ReturnRequest::with([
        'user',
        'product',
        'orderItem.store',
        'orderItem.order'
    ])
        ->whereHas('orderItem', function ($q) use ($seller_id) {
            $q->where('seller_id', $seller_id);
        });

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->whereHas('user', fn($q) => $q->where('username', 'like', "%$search%"))
                ->orWhereHas('product', fn($q) => $q->where('name', 'like', "%$search%"))
                ->orWhereHas('orderItem.store', fn($q) => $q->where('name', 'like', "%$search%"))
                ->orWhere('id', 'like', "%$search%")
                ->orWhereHas('orderItem', fn($q) => $q->where('order_id', 'like', "%$search%"));
        });
    }

    $total = $query->count();

    $returnRequests = $query->orderBy($sort, $order)
        ->skip($offset)
        ->take($limit)
        ->get();

    $translationService = app(TranslationService::class);

    $dataArray = [];

    foreach ($returnRequests as $item) {
        // dd($item);
        // Translate product name if applicable
        if ($item->product) {
            $item->product->translated_name = $translationService->getDynamicTranslation(
                Product::class,
                'name',
                $item->product->id,
                $languageCode
            );
        }
        $dataArray[] = [
            'id' => $item->id,
            'reason' => $item->remarks,
            'status' => $item->status,
            'created_at' => $item->created_at,
            'product_id' => $item->product->id ?? null,
            'product_name' => $item->product->translated_name ?? "",
            'product_image' => app(MediaService::class)->getMediaImageUrl($item->product->image),
            'product_type' => $item->product->type ?? "",
            'order_item_id' => $item->order_item_id,
            'username' => $item->user->username ?? "",
            'order_id' => $item->order_id,
            'payment_method' => $item->orderItem->order->payment_method ?? "",
            'quantity' => $item->orderItem->quantity ?? "",
            'sub_total' => $item->orderItem->sub_total,
            'price' => !empty($item->orderItem) ? $item->orderItem->price : '',
            'discounted_price' => !empty($item->orderItem) ? $item->orderItem->discounted_price : '',
            'store_name' => $translationService->getDynamicTranslation(
                Store::class,
                'name',
                $item->orderItem->store->id,
                $languageCode
            ) ?? "",
        ];
    }

    return [
        'total' => $total,
        'data' => $dataArray
    ];
}