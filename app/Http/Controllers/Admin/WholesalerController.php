<?php

namespace App\Http\Controllers\Admin;

use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/** Admin-side moderation for the Wholesaler module: account list (activate/suspend) and the per-listing
 * approval queue every new/edited wholesaler_products row goes through before sellers can browse/import it
 * (see Wholesaler\ProductController::store()). */
class WholesalerController extends Controller
{
    public function index()
    {
        return view('admin.pages.views.wholesalers.index');
    }

    public function list(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $search = trim((string) $request->input('search', ''));

        $query = Wholesaler::with('user')
            ->when($search !== '', function ($q) use ($search) {
                $q->where('business_name', 'like', "%$search%")
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('username', 'like', "%$search%")->orWhere('mobile', 'like', "%$search%");
                    });
            });

        $total = $query->count();
        $wholesalers = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $rows = $wholesalers->map(function ($w) {
            return [
                'id' => $w->id,
                'business_name' => $w->business_name,
                'username' => optional($w->user)->username,
                'mobile' => optional($w->user)->mobile,
                'products_count' => WholesalerProduct::where('wholesaler_id', $w->id)->count(),
                'status' => (int) $w->status === 1
                    ? '<span class="badge bg-success">' . labels('wholesaler_labels.active', 'Active') . '</span>'
                    : '<span class="badge bg-danger">' . labels('wholesaler_labels.inactive', 'Inactive') . '</span>',
                'operate' => '<a href="' . route('admin.wholesalers.toggle_status', $w->id) . '" class="btn btn-sm ' . ((int) $w->status === 1 ? 'btn-warning' : 'btn-success') . ' toggle-wholesaler-status">'
                    . ((int) $w->status === 1 ? labels('wholesaler_labels.suspend', 'Suspend') : labels('wholesaler_labels.activate', 'Activate')) . '</a>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function toggleStatus($id)
    {
        $wholesaler = Wholesaler::findOrFail($id);
        $wholesaler->status = (int) $wholesaler->status === 1 ? 0 : 1;
        $wholesaler->save();

        return response()->json(['message' => labels('wholesaler_labels.status_updated', 'Status updated successfully.')]);
    }

    public function productsQueue()
    {
        return view('admin.pages.views.wholesalers.products_queue');
    }

    public function productsList(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'DESC');
        $statusFilter = $request->input('status_filter', '0');

        $query = WholesalerProduct::with('wholesaler')
            ->when($statusFilter !== '', function ($q) use ($statusFilter) {
                $q->where('status', (int) $statusFilter);
            });

        $total = $query->count();
        $products = $query->orderBy($sort, $order)->skip($offset)->take($limit)->get();

        $statusLabels = [
            0 => '<span class="badge bg-warning">' . labels('wholesaler_labels.pending_approval', 'Pending Approval') . '</span>',
            1 => '<span class="badge bg-success">' . labels('wholesaler_labels.active', 'Active') . '</span>',
            2 => '<span class="badge bg-danger">' . labels('wholesaler_labels.rejected', 'Rejected') . '</span>',
        ];

        $rows = $products->map(function ($p) use ($statusLabels) {
            $name = json_decode($p->name, true);
            return [
                'id' => $p->id,
                'image' => '<img src="' . app(MediaService::class)->getMediaImageUrl($p->image) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">',
                'name' => $name['en'] ?? '',
                'wholesaler' => optional($p->wholesaler)->business_name,
                'wholesale_price' => $p->wholesale_price,
                'status' => $statusLabels[(int) $p->status] ?? $p->status,
                'operate' => (int) $p->status !== 1
                    ? '<a href="' . route('admin.wholesalers.products.approve', $p->id) . '" class="btn btn-sm btn-success approve-wholesaler-product"><i class="bx bx-check"></i></a> '
                    . '<a href="' . route('admin.wholesalers.products.reject', $p->id) . '" class="btn btn-sm btn-danger reject-wholesaler-product"><i class="bx bx-x"></i></a>'
                    : '<a href="' . route('admin.wholesalers.products.reject', $p->id) . '" class="btn btn-sm btn-danger reject-wholesaler-product">' . labels('wholesaler_labels.deactivate', 'Deactivate') . '</a>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function approveProduct($id)
    {
        $product = WholesalerProduct::findOrFail($id);
        $product->status = 1;
        $product->save();

        return response()->json(['message' => labels('wholesaler_labels.product_approved', 'Product approved and now visible to sellers.')]);
    }

    public function rejectProduct($id)
    {
        $product = WholesalerProduct::findOrFail($id);
        $product->status = 2;
        $product->save();

        return response()->json(['message' => labels('wholesaler_labels.product_rejected', 'Product rejected.')]);
    }
}
