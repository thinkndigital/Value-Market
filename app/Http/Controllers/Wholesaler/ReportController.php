<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Sales report for the wholesaler panel (docs/WHOLESALER_MODULE.md v2's "مبيعات" ask) - derived entirely
 * from wholesale_orders, since that's the only real transaction record this module has (no separate ledger/
 * payment-gateway integration - see the module doc's "out of scope" section). */
class ReportController extends Controller
{
    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        $wholesaler = $this->currentWholesaler();

        $delivered = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->where('status', WholesaleOrder::STATUS_DELIVERED);

        $totalRevenue = (clone $delivered)->sum('total_amount');
        $totalOrders = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->count();
        $deliveredCount = (clone $delivered)->count();
        $pendingCount = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->where('status', WholesaleOrder::STATUS_PENDING)->count();
        $unpaidAmount = WholesaleOrder::where('wholesaler_id', $wholesaler->id)
            ->where('status', WholesaleOrder::STATUS_DELIVERED)
            ->where('payment_status', 0)
            ->sum('total_amount');

        $topProducts = (clone $delivered)
            ->select('wholesaler_product_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(total_amount) as total_revenue'))
            ->groupBy('wholesaler_product_id')
            ->orderByDesc('total_revenue')
            ->with('wholesalerProduct')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $name = json_decode(optional($row->wholesalerProduct)->name, true);
                return ['name' => $name['en'] ?? '', 'qty' => (int) $row->total_qty, 'revenue' => $row->total_revenue];
            });

        $topBuyers = (clone $delivered)
            ->select('seller_id', DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total_amount) as total_revenue'))
            ->groupBy('seller_id')
            ->orderByDesc('total_revenue')
            ->with('seller.user')
            ->limit(5)
            ->get()
            ->map(fn($row) => [
                'name' => optional(optional($row->seller)->user)->username ?? ('Seller #' . $row->seller_id),
                'orders_count' => (int) $row->orders_count,
                'revenue' => $row->total_revenue,
            ]);

        return view('wholesaler.pages.views.reports.sales', compact(
            'totalRevenue', 'totalOrders', 'deliveredCount', 'pendingCount', 'unpaidAmount', 'topProducts', 'topBuyers'
        ));
    }
}
