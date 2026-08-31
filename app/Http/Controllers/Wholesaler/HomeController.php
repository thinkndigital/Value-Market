<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Product;
use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index()
    {
        $wholesaler = Wholesaler::where('user_id', Auth::id())->firstOrFail();

        $totalProducts = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->count();
        $pendingApproval = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->where('status', 0)->count();
        $activeProducts = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->where('status', 1)->count();
        $sellersImporting = Product::whereIn('wholesaler_product_id', WholesalerProduct::where('wholesaler_id', $wholesaler->id)->pluck('id'))
            ->distinct('seller_id')
            ->count('seller_id');

        $pendingOrders = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->where('status', WholesaleOrder::STATUS_PENDING)->count();
        $totalRevenue = WholesaleOrder::where('wholesaler_id', $wholesaler->id)->where('status', WholesaleOrder::STATUS_DELIVERED)->sum('total_amount');

        return view('wholesaler.pages.views.home', compact(
            'wholesaler', 'totalProducts', 'pendingApproval', 'activeProducts', 'sellersImporting', 'pendingOrders', 'totalRevenue'
        ));
    }
}
