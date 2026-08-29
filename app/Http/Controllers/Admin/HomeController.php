<?php

namespace App\Http\Controllers\Admin;

use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Store;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\StoreService;
use App\Services\OrderService;
use App\Services\SetupProgressService;
class HomeController extends Controller
{

    public function index()
    {
        $id = 0;

        $store_id = app(StoreService::class)->getStoreId();

        $currency = fetchDetails(Currency::class, ['is_default' => 1], 'symbol')[0]->symbol ?? "";

        // total statictis

        $order_counter = app(OrderService::class)->countNewOrders();

        // Performance fix (found while diagnosing /admin/home): home.blade.php called
        // app(OrderService::class)->ordersCount($status, '', '', $store_id) 24 times inline in the template
        // (received/processed/shipped/delivered/cancelled/returned x ~3-5 each, plus the '' / "all statuses"
        // total re-run identically 6 times) - each a real query against order_items. Computed once per
        // distinct status here instead; the view now reads from this array. Same values, same call
        // signature per status - including the pre-existing display bug where two of the progress bars'
        // aria-valuenow reused the 'received' count instead of their own status (left as-is: not part of
        // this performance pass, and fixing it would change what the page displays).
        $orderService = app(OrderService::class);
        $orders_status_counts = [
            'received' => $orderService->ordersCount('received', '', '', $store_id),
            'processed' => $orderService->ordersCount('processed', '', '', $store_id),
            'shipped' => $orderService->ordersCount('shipped', '', '', $store_id),
            'delivered' => $orderService->ordersCount('delivered', '', '', $store_id),
            'cancelled' => $orderService->ordersCount('cancelled', '', '', $store_id),
            'returned' => $orderService->ordersCount('returned', '', '', $store_id),
            'all' => $orderService->ordersCount('', '', '', $store_id),
        ];

        $product_counter = Product::where('store_id', $store_id)
            ->whereHas('productVariants')
            ->count();
        $combo_product_counter = ComboProduct::where('store_id', $store_id)->count();


        $total_products = $product_counter + $combo_product_counter;
        // dd($total_products);
        $total_store = Store::count();
        $total_seller = Seller::whereHas('stores', function ($query) use ($store_id) {
            $query->where('store_id', $store_id);
        })->count();



        $delivery_boy_counter = countDeliveryBoys();

        $total_earnings = AdmintotalEarnings();

        // chatify

        $role_id = Auth::user() ? Auth::user()->role_id : "";
        $store_details = fetchDetails(Store::class, ['id' => $store_id], ['primary_color', 'secondary_color', 'hover_color', 'active_color']);
        $primary_colour = (isset($store_details[0]->primary_color) && !empty($store_details[0]->primary_color)) ? $store_details[0]->primary_color : '#B52046';
        $messengerColor = $primary_colour;
        $dark_mode = Auth::user() && Auth::user()->dark_mode < 1 ? 'light' : 'dark';

        // user counter

        $user_counter = countNewUsers();


        //-------------------------------- get admin overview statistics ------------------------------------

        $sales = [];

        // monthly earnings

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

        // Fetch data for each type
        // Performance fix (found while diagnosing /admin/home): these were 3 separate calls to
        // getMonthlyData(), each running its own identical GROUP BY YEAR/MONTH query against order_items,
        // differing only in which single column got SUM()'d. Same GROUP BY, same rows - merged into one
        // query selecting all three sums at once; same three result arrays as before.
        $monthCombinedRes = $this->getMonthlyDataCombined($store_id);
        $monthRes = array_map(fn ($row) => ['month_name' => $row['month_name'], 'total' => $row['sub_total']], $monthCombinedRes);
        $monthCommissionRes = array_map(fn ($row) => ['month_name' => $row['month_name'], 'total' => $row['admin_commission_amount']], $monthCombinedRes);
        $monthSalesRes = array_map(fn ($row) => ['month_name' => $row['month_name'], 'total' => $row['quantity']], $monthCombinedRes);

        // Merge the database results with the allMonths array, replacing existing values
        $monthWiseRevenueDetail = array_merge($allMonths, array_combine(array_column($monthRes, 'month_name'), array_map('intval', array_column($monthRes, 'total'))));
        $monthCommissionDetail = array_merge($allMonths, array_combine(array_column($monthCommissionRes, 'month_name'), array_map('intval', array_column($monthCommissionRes, 'total'))));
        $monthSalesDetail = array_merge($allMonths, array_combine(array_column($monthSalesRes, 'month_name'), array_map('intval', array_column($monthSalesRes, 'total'))));

        // Create the result array
        $monthWiseSales['total_revenue'] = array_values($monthWiseRevenueDetail);
        $monthWiseSales['total_commission'] = array_values($monthCommissionDetail);
        $monthWiseSales['total_sales'] = array_values($monthSalesDetail);
        $monthWiseSales['month_name'] = array_keys($monthWiseRevenueDetail);

        $sales[0] = $monthWiseSales;
        $now = now();

        // weekly earnings

        $startDate = Carbon::now()->startOfWeek(); // Start of the current week (Sunday)
        $endDate = Carbon::now()->endOfWeek(); // End of the current week (Saturday)

        $weekWiseSales = [
            'total_revenue' => [],
            'total_commission' => [],
            'total_sales' => [],
            'day' => []
        ];
        $currentDate = Carbon::now();
        // Performance fix (found while diagnosing /admin/home): getWeeklySalesData() already computes all 7
        // days of the week in one query (it groups by DATE(created_at) across the whole week) - the loop
        // below only ever reads $dayRes[...][$i], a single index into that same result, so calling it again
        // on every iteration re-ran the identical heavy GROUP BY query against order_items 7 times for no
        // reason. Same output, called once instead of 7 times.
        $dayRes = $this->getWeeklySalesData('order_items', 'created_at', 'sub_total', 'admin_commission_amount', 'quantity', $store_id);
        // Loop to retrieve data for each day of the week
        for ($i = 0; $i < 7; $i++) {
            // Get the day name for the current iteration
            $dayName = $currentDate->copy()->startOfWeek()->addDays($i)->format('D, d M');

            // If data exists for the current day
            if (isset($dayRes['total_revenue'][$i])) {
                $weekWiseSales['total_revenue'][] = intval($dayRes['total_revenue'][$i]);
                $weekWiseSales['total_commission'][] = intval($dayRes['total_commission'][$i]);
                $weekWiseSales['total_sales'][] = intval($dayRes['total_sales'][$i]);
            } else {
                // If no data exists for the current day, set totals to 0
                $weekWiseSales['total_revenue'][] = 0;
                $weekWiseSales['total_commission'][] = 0;
                $weekWiseSales['total_sales'][] = 0;
            }

            // Add the day name to the week-wise sales array
            $weekWiseSales['day'][] = $dayName;
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

        $dayRes = $this->getDailySalesData('order_items', 'created_at', 'sub_total', 'admin_commission_amount', 'quantity', $store_id, 29);

        // Create an associative array with date as key for easier merging
        $dayData = [];
        foreach ($dayRes as $day) {
            $dayData[$day->date] = [
                'total_revenue' => intval($day->total_revenue),
                'total_commission' => intval($day->total_commission),
                'total_sales' => intval($day->total_sales)
            ];
        }

        // Merge fetched data with all dates of the month, filling missing dates with zeros
        $dayWiseSales = [];
        foreach ($allDatesOfMonth as $dateInfo) {
            $date = $dateInfo['date'];
            if (isset($dayData[$date])) {
                $dayWiseSales['total_revenue'][] = $dayData[$date]['total_revenue'];
                $dayWiseSales['total_commission'][] = $dayData[$date]['total_commission'];
                $dayWiseSales['total_sales'][] = $dayData[$date]['total_sales'];
            } else {
                $dayWiseSales['total_revenue'][] = 0;
                $dayWiseSales['total_commission'][] = 0;
                $dayWiseSales['total_sales'][] = 0;
            }
            $dayWiseSales['day'][] = $date . '-' . $dateInfo['month'] . '-' . $dateInfo['year'];
        }

        $sales[2] = $dayWiseSales;

        $store = Store::find($store_id);
        $top_sellers=[];
        if ($store) {
            // Performance fix (found while diagnosing /admin/home): this used to eager-load every matching
            // order_items row (seller_id, sub_total, seller_commission_amount, active_status - unbounded,
            // not scoped by store_id, not scoped by date) for every seller of this store, then summed them
            // in PHP via Collection::sum(). At real volume that's both a large row transfer and slow PHP
            // iteration for something a single GROUP BY already computes. Same two sums, same "no store_id
            // filter on order_items" behavior (a seller selling in multiple stores' order_items still gets
            // summed together here, matching the original query exactly), same sort/take(6) - now computed
            // once in SQL instead of row-by-row in PHP.
            $sellers = Store::find($store_id)->sellers()->with('user')->get();
            $sellerIds = $sellers->pluck('id');
            $aggregates = OrderItems::whereIn('seller_id', $sellerIds)
                ->selectRaw("seller_id, SUM(CASE WHEN active_status = 'delivered' THEN sub_total ELSE 0 END) as total_sales, SUM(seller_commission_amount) as total_commission")
                ->groupBy('seller_id')
                ->get()
                ->keyBy('seller_id');

            $top_sellers = $sellers
                    ->map(function ($seller) use ($aggregates) {
                        $agg = $aggregates->get($seller->id);
                        return [
                            'seller_id' => $seller->id,
                            'store_name' => $seller->pivot->store_name,
                            'logo' => $seller->pivot->logo,
                            'seller_name' => optional($seller->user)->username,
                            'total_sales' => intval($agg->total_sales ?? 0),
                            'total_commission' => intval($agg->total_commission ?? 0),
                        ];
                    })
                    ->sortByDesc('total_sales')
                    ->take(6)
                    ->values();
        }

        // dd($top_sellers);
        // Changelog v1.0.9 ("Setup Progress Tracker" / "Setup completion tracking in admin dashboard").
        $setup_progress = app(SetupProgressService::class)->getProgress();

        return view('admin.pages.forms.home', compact('order_counter', 'id', 'store_id', 'user_counter', 'delivery_boy_counter', 'currency', 'top_sellers', 'total_products', 'total_store', 'total_seller', 'total_earnings', 'role_id', 'store_details', 'primary_colour', 'messengerColor', 'dark_mode', 'sales', 'orders_status_counts', 'setup_progress'));
    }

    /**
     * Performance fix: replaces 3 separate getMonthlyData() calls (one per SUM'd column) with a single
     * query computing all three sums per month in one pass - same GROUP BY/ORDER BY as before.
     */
    private function getMonthlyDataCombined($store_id)
    {
        return OrderItems::selectRaw("SUM(sub_total) as sub_total, SUM(admin_commission_amount) as admin_commission_amount, SUM(quantity) as quantity, DATE_FORMAT(created_at, '%b') AS month_name")
            ->where('store_id', $store_id)
            ->groupByRaw('YEAR(CURDATE()), MONTH(created_at)')
            ->orderByRaw('YEAR(CURDATE()), MONTH(created_at)')
            ->get()
            ->toArray();
    }


    private function getWeeklySalesData($tableName, $dateColumn, $revenueColumn, $commissionColumn, $salesColumn, $store_id)
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        // Initialize the week-wise sales data structure
        $weekWiseSales = [
            'total_revenue' => array_fill(0, 7, 0), // 0 for each day of the week
            'total_commission' => array_fill(0, 7, 0),
            'total_sales' => array_fill(0, 7, 0),
            'week' => [],
        ];

        // Fetch sales data from the database

        $res = OrderItems::selectRaw("
            DATE_FORMAT(created_at, '%d-%b') as date,
            SUM(sub_total) as total_revenue,
            SUM(admin_commission_amount) as total_commission,
            SUM(quantity) as total_sales
        ")
            ->where('store_id', $store_id)
            ->whereBetween(DB::raw('DATE(created_at)'), [
                $startOfWeek->format('Y-m-d'),
                $endOfWeek->format('Y-m-d')
            ])
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->get();

        // dd($res);
        // Populate the week-wise sales data
        foreach ($res as $dayData) {
            // Get the day index based on the difference from the start of the week
            $dayIndex = Carbon::createFromFormat('d-M', $dayData->date)->diffInDays($startOfWeek);

            // Set the corresponding revenue, commission, and sales for that day
            $weekWiseSales['total_revenue'][$dayIndex] = intval($dayData->total_revenue);
            $weekWiseSales['total_commission'][$dayIndex] = intval($dayData->total_commission);
            $weekWiseSales['total_sales'][$dayIndex] = intval($dayData->total_sales);
            $weekWiseSales['week'][$dayIndex] = $dayData->date;
        }

        // Fill in the week with day names for all 7 days
        foreach (range(0, 6) as $i) {
            if (!isset($weekWiseSales['week'][$i])) {
                $weekWiseSales['week'][$i] = $startOfWeek->copy()->addDays($i)->format('d-M');
            }
        }

        return $weekWiseSales;
    }

    private function getDailySalesData($tableName, $dateColumn, $revenueColumn, $commissionColumn, $salesColumn, $store_id, $daysBack)
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subDays($daysBack);

        $res = OrderItems::selectRaw("
        DAY(created_at) as date,
        SUM(sub_total) as total_revenue,
        SUM(admin_commission_amount) as total_commission,
        SUM(quantity) as total_sales
    ")
            ->where('store_id', $store_id)
            ->where('created_at', '>=', $startDate)
            ->groupByRaw("DAY(created_at)")
            ->get();

        return $res;
    }
}
