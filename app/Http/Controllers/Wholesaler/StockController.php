<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Services\MediaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/** Dedicated stock view for the wholesaler's own catalog (docs/WHOLESALER_MODULE.md v2's "مخزون" ask) -
 * quick add/subtract stock adjustments without opening the full product edit form. */
class StockController extends Controller
{
    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    public function index()
    {
        return view('wholesaler.pages.views.stock.index');
    }

    public function list(Request $request)
    {
        $wholesaler = $this->currentWholesaler();
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 10);
        $search = trim((string) $request->input('search', ''));

        $query = WholesalerProduct::where('wholesaler_id', $wholesaler->id)
            ->when($search !== '', fn($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) like ?", ["%$search%"]));

        $total = $query->count();
        $products = $query->orderBy('stock', 'ASC')->skip($offset)->take($limit)->get();

        $rows = $products->map(function ($p) {
            $name = json_decode($p->name, true);
            return [
                'id' => $p->id,
                'image' => '<img src="' . app(MediaService::class)->getMediaImageUrl($p->image) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">',
                'name' => $name['en'] ?? '',
                'stock' => (int) $p->stock < 20
                    ? '<span class="text-danger fw-bold">' . $p->stock . '</span>'
                    : (string) $p->stock,
                'operate' => '<button type="button" class="btn btn-sm btn-outline-secondary adjust-stock-btn" data-id="' . $p->id . '" data-stock="' . $p->stock . '">' . labels('wholesaler_labels.adjust_stock', 'Adjust Stock') . '</button>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function adjust(Request $request, $id)
    {
        $wholesaler = $this->currentWholesaler();
        $product = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->find($id);
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $request->validate(['delta' => 'required|integer']);

        $newStock = max(0, (int) $product->stock + (int) $request->delta);
        $product->stock = $newStock;
        $product->save();

        return response()->json(['message' => labels('wholesaler_labels.stock_updated', 'Stock updated successfully.'), 'stock' => $newStock]);
    }
}
