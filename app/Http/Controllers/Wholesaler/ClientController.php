<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** "My Buyers" (docs/WHOLESALER_MODULE.md v2's "CRM وعملاء" ask) - confirmed with the product owner that a
 * wholesaler's "clients" are the sellers who order from it, not end consumers, so this is a per-seller
 * purchase-history rollup rather than a full notes/tags CRM (the existing customer_notes/customer_tags
 * tables are keyed on customer_user_id and don't fit a seller-as-client relationship - see this controller's
 * own docblock in the module doc for why a parallel schema wasn't built for v1 of this). */
class ClientController extends Controller
{
    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        return view('wholesaler.pages.views.clients.index');
    }

    public function list(Request $request)
    {
        $wholesaler = $this->currentWholesaler();
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);

        $query = WholesaleOrder::where('wholesaler_id', $wholesaler->id)
            ->select(
                'seller_id',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw("SUM(CASE WHEN status = " . WholesaleOrder::STATUS_DELIVERED . " THEN total_amount ELSE 0 END) as total_spent"),
                DB::raw('MAX(created_at) as last_order_at')
            )
            ->groupBy('seller_id')
            ->with('seller.user');

        $total = (clone $query)->get()->count();
        $clients = $query->orderByDesc('total_spent')->skip($offset)->take($limit)->get();

        $rows = $clients->map(fn($row) => [
            'seller' => optional(optional($row->seller)->user)->username ?? ('Seller #' . $row->seller_id),
            'mobile' => optional(optional($row->seller)->user)->mobile,
            'orders_count' => (int) $row->orders_count,
            'total_spent' => $row->total_spent,
            'last_order_at' => optional($row->last_order_at)
                ? \Illuminate\Support\Carbon::parse($row->last_order_at)->format('Y-m-d H:i')
                : '',
        ]);

        return response()->json(['rows' => $rows, 'total' => $total]);
    }
}
