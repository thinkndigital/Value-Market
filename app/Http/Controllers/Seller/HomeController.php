<?php

namespace App\Http\Controllers\Seller;

use App\Models\Category;
use App\Models\ComboProductRating;
use App\Models\Currency;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\TranslationService;
use App\Services\StoreService;
use App\Services\MediaService;
class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $id =  0;
        $currencyDetails = fetchDetails(Currency::class, ['is_default' => 1], 'symbol');
        $currency = !$currencyDetails->isEmpty() ? $currencyDetails[0]->symbol : '';
        $dark_mode = Auth::user()->dark_mode < 1 ? 'light' : 'dark';
        $role_id = Auth::user()->role_id;
        $store_id = app(StoreService::class)->getStoreId();
        $language_code = app(TranslationService::class)->getLanguageCode();
        $store_details = fetchDetails(Store::class, ['id' => $store_id], ['primary_color', 'secondary_color', 'hover_color', 'active_color']);
        $primary_colour = (isset($store_details[0]->primary_color) && !empty($store_details[0]->primary_color)) ?  $store_details[0]->primary_color : '#B52046';
        $messengerColor = $primary_colour;
        $user_id = Auth::id();
        $seller_id = Seller::where('user_id', $user_id)->value('id');
        $total_balance = fetchDetails(User::class, ['id' => $user_id], 'balance')[0]->balance;
        $overallSale = OrderItems::where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->where('active_status', 'delivered')
            ->sum('sub_total');


        // -------------------------- get latest product ratings -----------------------------------


        $latestRatings = ProductRating::with(['product.productVariants', 'product', 'user'])
            ->whereHas('product', function ($q) use ($seller_id, $store_id) {
                $q->where('seller_id', $seller_id)
                    ->where('store_id', $store_id);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($rating) {
                $product = $rating->product;
                $variant = null;

                if ($product && $product->type == 'simple_product') {
                    $variant = $product->productVariants->first();
                } elseif ($product && $product->type == 'variable_product') {

                    $variant = $product->productVariants->first();
                }

                return [
                    'id' => $rating->id,
                    'rating' => $rating->rating,
                    'comment' => $rating->comment,
                    'created_at' => $rating->created_at->format('Y-m-d H:i:s'),
                    'product_name' => $product?->name ?? '',
                    'product_id' => $product?->id ?? '',
                    'product_image' => app(MediaService::class)->getMediaImageUrl($product?->image),
                    'price' => $variant?->price ?? '',
                    'special_price' => $variant?->special_price ?? '',
                    'username' => $rating->user?->username ?? '',
                    'user_image' => $rating->user?->image ? asset(config('constants.USER_IMG_PATH') . $rating->user->image) : null,
                ];
            });

        //-------------------------------- get seller order overview statistics ------------------------------------

        $sales = [];

        // monthly earnings

        $monthRes = OrderItems::selectRaw('
            SUM(quantity) AS total_sale,
            SUM(sub_total) AS total_revenue,
            COUNT(*) AS total_orders,
            DATE_FORMAT(created_at, "%b") AS month_name
        ')
            ->where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();



        $allMonths = [
            'Jan' => 0,
            'Feb' => 0,
            'Mar' => 0,
            'Apr' => 0,
            'May' => 0,
            'Jun' => 0,
            'Jul' => 0,
            'Aug' => 0,
            'Sep' => 0,
            'Oct' => 0,
            'Nov' => 0,
            'Dec' => 0
        ];

        // Merge the database results with the allMonths array, replacing existing values

        $monthWiseSalesDetail = $allMonths;

        foreach ($monthRes as $month) {
            $monthName = $month->month_name;
            $totalSale = intval($month->total_sale);
            $totalOrders = intval($month->total_orders);
            $totalRevenue = intval($month->total_revenue);

            $monthWiseSalesDetail[$monthName] = [
                'total_sale' => $totalSale,
                'total_orders' => $totalOrders,
                'total_revenue' => $totalRevenue
            ];
        }

        // Extracting individual arrays
        $totalSales = [];
        $totalOrders = [];
        $totalRevenues = [];

        foreach ($monthWiseSalesDetail as $monthName => $monthData) {
            $totalSales[] = isset($monthData['total_sale']) ? $monthData['total_sale'] : 0;
            $totalOrders[] = isset($monthData['total_orders']) ? $monthData['total_orders'] : 0;
            $totalRevenues[] = isset($monthData['total_revenue']) ? $monthData['total_revenue'] : 0;
        }

        $monthWiseSales['total_sale'] = $totalSales;
        $monthWiseSales['total_orders'] = $totalOrders;
        $monthWiseSales['total_revenue'] = $totalRevenues;
        $monthWiseSales['month_name'] = array_keys($monthWiseSalesDetail);

        $sales[0] = $monthWiseSales;
        // weekly earnings

        //this is for current week data
        $startDate = Carbon::now()->startOfWeek(); // Start of the current week (Sunday)
        $endDate = Carbon::now()->endOfWeek(); // End of the current week (Saturday)





        // Initialize an array to hold the data for each day of the week
        $weekWiseSales = [
            'total_sale' => [],
            'day' => []
        ];

        $allDaysOfWeek = [
            'Sunday' => 0,
            'Monday' => 0,
            'Tuesday' => 0,
            'Wednesday' => 0,
            'Thursday' => 0,
            'Friday' => 0,
            'Saturday' => 0
        ];

        // Loop to retrieve data for each day of the week
        for ($i = 0; $i < 7; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $dayName = $currentDate->englishDayOfWeek; // Get the day name (e.g., "Sunday", "Monday")

            $dayRes = OrderItems::where('seller_id', $seller_id)
                ->where('store_id', $store_id)
                ->whereDate('created_at', $currentDate->format('Y-m-d'))
                ->selectRaw('SUM(quantity) as total_sale, SUM(sub_total) as total_revenue, COUNT(*) as total_orders')
                ->first();

            // If data exists for the current day, add it to the weekWiseSales array
            if ($dayRes) {
                $weekWiseSales['total_sale'][] = intval($dayRes->total_sale);
                $weekWiseSales['total_revenue'][] = intval($dayRes->total_revenue);
                $weekWiseSales['total_orders'][] = intval($dayRes->total_orders);
                $weekWiseSales['day'][] = $dayName;
            } else {
                // If no data exists for the current day, set total_sale, total_revenue, and total_orders to 0
                $weekWiseSales['total_sale'][] = 0;
                $weekWiseSales['total_revenue'][] = 0;
                $weekWiseSales['total_orders'][] = 0;
                $weekWiseSales['day'][] = $dayName;
            }
        }

        $sales[1] = $weekWiseSales;



        // daily earnings

        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays(29);

        // Create an array with all dates of the month
        $allDatesOfMonth = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $allDatesOfMonth[] = [
                'date' => $currentDate->format('j'),
                'month' => $currentDate->format('M'),
                'year' => $currentDate->format('Y')
            ];
            $currentDate->addDay();
        }

        // Fetch data from the database
        $dayRes = OrderItems::where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DAY(created_at) as date, SUM(quantity) as total_sale, SUM(sub_total) as total_revenue, COUNT(*) as total_orders")
            ->groupBy(DB::raw('DAY(created_at)'))
            ->get();

        // Create an associative array with date as key for easier merging
        $dayData = [];
        foreach ($dayRes as $day) {
            $dayData[$day->date] = [
                'total_sale' => intval($day->total_sale),
                'total_revenue' => intval($day->total_revenue),
                'total_orders' => intval($day->total_orders)
            ];
        }

        // Merge fetched data with all dates of the month, filling missing dates with zeros
        $dayWiseSales = [];
        foreach ($allDatesOfMonth as $dateInfo) {
            $date = $dateInfo['date'];
            if (isset($dayData[$date])) {
                $dayWiseSales['total_sale'][] = $dayData[$date]['total_sale'];
                $dayWiseSales['total_revenue'][] = $dayData[$date]['total_revenue'];
                $dayWiseSales['total_orders'][] = $dayData[$date]['total_orders'];
            } else {
                $dayWiseSales['total_sale'][] = 0;
                $dayWiseSales['total_revenue'][] = 0;
                $dayWiseSales['total_orders'][] = 0;
            }
            $dayWiseSales['day'][] = $date . '-' . $dateInfo['month'] . '-' . $dateInfo['year'];
        }

        $sales[2] = $dayWiseSales;


        // ============================= Most popular category data for chart ================================
        $topSellingCategories = [];

        //monthly data for category chart

        $firstDayOfMonth = Carbon::now()->startOfMonth();
        $lastDayOfMonth = Carbon::now()->endOfMonth();

        $topSellingCategoriesDataMonthRes = OrderItems::with(['productVariant.product.category'])
            ->where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->whereBetween('created_at', [$firstDayOfMonth, $lastDayOfMonth])
            ->get()
            ->filter(function ($item) {
                return optional(optional(optional($item->productVariant)->product)->category)->id !== null;
            })
            ->groupBy(function ($item) {
                return optional($item->productVariant?->product?->category)->id;
            })
            ->map(function ($items, $categoryId) {
                $category = optional($items->first()->productVariant?->product?->category);
                return [
                    'category_id' => $categoryId,
                    'category_name' => $category->name,
                    'total_sold' => $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(5)
            ->values();

        // Apply dynamic translation to category names
        $monthlyTopSellingCategoriesData['totalSold'] = $topSellingCategoriesDataMonthRes->pluck('total_sold');

        $monthlyTopSellingCategoriesData['categoryNames'] = $topSellingCategoriesDataMonthRes->map(function ($item) use ($language_code) {
            return app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $item['category_id'], $language_code);
        });


        $topSellingCategories[0] = $monthlyTopSellingCategoriesData;



        //Yearly data for category chart

        $currentYear = Carbon::now()->year;

        $firstDayOfYear = Carbon::create($currentYear, 1, 1)->startOfDay();
        $lastDayOfYear = Carbon::create($currentYear, 12, 31)->endOfDay();


        $topSellingCategoriesDataYearRes = OrderItems::with(['productVariant.product.category'])
            ->where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->whereBetween('created_at', [$firstDayOfYear, $lastDayOfYear])
            ->get()
            ->filter(function ($item) {
                return optional(optional(optional($item->productVariant)->product)->category)->id !== null;
            })
            ->groupBy(function ($item) {
                return optional($item->productVariant?->product?->category)->id;
            })
            ->map(function ($items, $categoryId) {
                $category = optional($items->first()->productVariant?->product?->category);
                return [
                    'category_id' => $categoryId,
                    'category_name' => $category->name,
                    'total_sold' => $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(5)
            ->values();


        $yearlyTopSellingCategoriesData['totalSold'] = $topSellingCategoriesDataYearRes->pluck('total_sold');

        $yearlyTopSellingCategoriesData['categoryNames'] = $topSellingCategoriesDataYearRes->map(function ($item) use ($language_code) {
            return app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $item['category_id'], $language_code);
        });

        // $yearlyTopSellingCategoriesData['categoryNames'] = $topSellingCategoriesDataYearRes->pluck('category_name');

        $topSellingCategories[1] = $yearlyTopSellingCategoriesData;

        //weekly data for category chart

        //for current week
        $startDate = Carbon::now()->startOfWeek();
        $endDate = Carbon::now()->endOfWeek();

        $topSellingCategoriesDataWeekRes = OrderItems::with(['productVariant.product.category'])
            ->where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->filter(fn($item) => optional($item->productVariant?->product?->category)->id !== null)
            ->groupBy(fn($item) => optional($item->productVariant?->product?->category)->id)
            ->map(function ($items, $categoryId) {
                $category = optional($items->first()->productVariant?->product?->category);
                return [
                    'category_id' => $categoryId,
                    'category_name' => $category->name,
                    'total_sold' => $items->sum('quantity'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(5)
            ->values();

        $weeklyTopSellingCategoriesData['totalSold'] = $topSellingCategoriesDataWeekRes->pluck('total_sold');

        $weeklyTopSellingCategoriesData['categoryNames'] = $topSellingCategoriesDataWeekRes->map(function ($item) use ($language_code) {
            return app(TranslationService::class)->getDynamicTranslation(Category::class, 'name', $item['category_id'], $language_code);
        });


        $topSellingCategories[2] = $weeklyTopSellingCategoriesData;

        $seller_rating =
            $topSellingCategoriesDataWeekRes = SellerStore::select('rating', 'no_of_ratings')
            ->where('seller_store.seller_id', '=', $seller_id)
            ->where('seller_store.store_id', '=', $store_id)->get();



        // ============================================ store average rating ===========================================


        $currentYear = Carbon::now()->year;

        // Initialize ratings array with 0 for all months of the current year
        $ratings = array_fill(1, 12, 0);

        // Get simple product ratings for the current year
        $simpleProductRatings = ProductRating::with('product.sellerData')
            ->whereHas('product.sellerData', function ($query) use ($store_id, $seller_id) {
                $query->where('store_id', $store_id)
                    ->where('seller_id', $seller_id);
            })
            ->whereYear('created_at', $currentYear)
            ->get();

        // Get combo product ratings for the current year
        $comboProductRatings = ComboProductRating::with('product.sellerData')
            ->whereHas('product.sellerData', function ($query) use ($store_id, $seller_id) {
                $query->where('store_id', $store_id)
                    ->where('seller_id', $seller_id);
            })
            ->whereYear('created_at', $currentYear)
            ->get();

        // Update ratings array with actual ratings for simple products
        foreach ($simpleProductRatings as $rating) {
            $month = $rating->created_at->month;
            $ratings[$month] += 1;
        }

        // Update ratings array with actual ratings for combo products
        foreach ($comboProductRatings as $rating) {
            $month = $rating->created_at->month;
            $ratings[$month] += 1;
        }

        // Calculate the total ratings and average rating for the whole year
        $totalRatings = array_sum($ratings);
        $averageRating = $totalRatings / 12;

        // Get month names
        $monthNames = [
            1 => "Jan",
            2 => "Feb",
            3 => "Mar",
            4 => "Apr",
            5 => "May",
            6 => "Jun",
            7 => "Jul",
            8 => "Aug",
            9 => "Sep",
            10 => "Oct",
            11 => "Nov",
            12 => "Dec"
        ];

        // Prepare the final output
        $store_rating = [
            'total_ratings' => array_values($ratings),
            'month_name' => array_map(function ($month) use ($monthNames) {
                return $monthNames[$month];
            }, array_keys($ratings)),
        ];

        $seller_categories = SellerStore::select('category_ids')
            ->where('store_id', $store_id)
            ->where('seller_id', $seller_id)
            ->get();

        $category_ids = $seller_categories->isNotEmpty()
            ? explode(",", $seller_categories[0]->category_ids)
            : [];

        $categories = Category::select('id', 'name')
            ->whereIn('id', $category_ids)
            ->where('status', 1)
            ->where('store_id', $store_id)
            ->get();
        return view('seller.pages.forms.home', compact('store_id', 'seller_id', 'id', 'messengerColor', 'dark_mode', 'role_id', 'currency', 'total_balance', 'overallSale', 'latestRatings', 'sales', 'language_code', 'topSellingCategories', 'seller_rating', 'store_rating', 'categories'));
    }

    public function topSellingProducts(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::id();
        $seller_id = Seller::where('user_id', $user_id)->value('id');
        $category_id = $request->input('category_id');
        $language_code = app(TranslationService::class)->getLanguageCode();

        $query = OrderItems::with(['productVariant.product'])
            ->where('store_id', $store_id)
            ->where('seller_id', $seller_id)
            ->when($category_id, function ($q) use ($category_id) {
                $q->whereHas('productVariant.product', function ($subQ) use ($category_id) {
                    $subQ->where('category_id', $category_id);
                });
            });

        // Group and aggregate total sold per product
        $items = $query->get()
            ->groupBy(fn($item) => $item->productVariant?->product?->id)
            ->map(function ($group) use ($language_code) {
                $product = $group->first()->productVariant?->product;

                if (!$product) {
                    return null;
                }

                return [
                    'product_image' => $product?->image ?? '',
                    'name' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code) ?? 'Unnamed Product',
                    'total_sold' => $group->sum('quantity'),
                ];
            })
            ->filter()
            ->sortByDesc('total_sold')
            ->take(5)
            ->values();
        return response()->json([
            'data' => $items
        ]);
    }
    public function mostPopularProduct(Request $request)
    {
        $store_id = app(StoreService::class)->getStoreId();
        $user_id = Auth::id();
        $seller_id = Seller::where('user_id', $user_id)->value('id');
        $category_id = $request->input('category_id');
        $language_code = $request->input('language_code', 'en');

        // Get all products for this seller & store
        $productsQuery = Product::with(['ratings', 'productVariants.orderItems'])
            ->where('seller_id', $seller_id)
            ->where('store_id', $store_id)
            ->when($category_id, fn($q) => $q->where('category_id', $category_id));

        $products = $productsQuery->get();

        // Map with rating calculations
        $topRated = $products->map(function ($product) use ($language_code) {
            $ratings = $product->ratings;
            $avgRating = $ratings->avg('rating');
            $totalReviews = $ratings->count();

            return [
                'product_image' => $product->image,
                'name' => app(TranslationService::class)->getDynamicTranslation(Product::class, 'name', $product->id, $language_code),
                'average_rating' => round($avgRating, 2),
                'total_reviews' => $totalReviews,
            ];
        })
            ->sortByDesc('average_rating')
            ->take(5)
            ->values();

        return response()->json([
            'data' => $topRated
        ]);
    }
}
