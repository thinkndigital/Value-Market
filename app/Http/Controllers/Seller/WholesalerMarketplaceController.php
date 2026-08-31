<?php

namespace App\Http\Controllers\Seller;

use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\WholesalerProduct;
use App\Services\MediaService;
use App\Services\StoreService;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "Browse/import" half of the Wholesaler Marketplace (see docs/WHOLESALER_MODULE.md): a seller browses
 * active (admin-approved) wholesaler_products rows and imports the ones they want to stock. Importing does
 * NOT touch the wholesaler's own listing - it creates a brand-new row in this seller's own `products` table
 * (same table their own catalog already lives in, so every existing storefront/order/stock/POS code path
 * just works with it unchanged), linked back via `wholesaler_product_id` for traceability. The seller sets
 * their own retail price and starting stock at import time; the wholesaler's `wholesale_price` is shown
 * only as a reference cost, never copied in as the retail price.
 */
class WholesalerMarketplaceController extends Controller
{
    use HandlesValidation;

    public function index()
    {
        return view('seller.pages.views.wholesaler_marketplace.index');
    }

    public function list(Request $request)
    {
        $offset = (int) $request->input('offset', 0);
        $limit = (int) $request->input('limit', 12);
        $search = trim((string) $request->input('search', ''));

        $query = WholesalerProduct::with('wholesaler')
            ->where('status', 1) // admin-approved only
            ->when($search !== '', function ($q) use ($search) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.en')) like ?", ["%$search%"]);
            });

        $total = $query->count();
        $products = $query->orderBy('id', 'DESC')->skip($offset)->take($limit)->get();

        $sellerId = Seller::where('user_id', Auth::id())->value('id');

        $rows = $products->map(function ($p) use ($sellerId) {
            $name = json_decode($p->name, true);
            $alreadyImported = Product::where('seller_id', $sellerId)->where('wholesaler_product_id', $p->id)->exists();

            return [
                'id' => $p->id,
                'image' => '<img src="' . app(MediaService::class)->getMediaImageUrl($p->image) . '" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">',
                'name' => $name['en'] ?? '',
                'wholesaler' => optional($p->wholesaler)->business_name,
                'wholesale_price' => $p->wholesale_price,
                'min_order_qty' => $p->min_order_qty,
                'stock' => $p->stock,
                'operate' => $alreadyImported
                    ? '<span class="badge bg-success">' . labels('wholesaler_labels.imported', 'Imported') . '</span>'
                    : '<button type="button" class="btn btn-sm btn-primary import-wholesaler-product" data-id="' . $p->id . '">' . labels('wholesaler_labels.import', 'Import') . '</button>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $total]);
    }

    public function import(Request $request, $id)
    {
        $wholesalerProduct = WholesalerProduct::where('status', 1)->find($id);
        if (!$wholesalerProduct) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $rules = [
            'retail_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        $storeId = app(StoreService::class)->getStoreId();
        if (!SellerStore::where('user_id', Auth::id())->where('store_id', $storeId)->exists()) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }
        $sellerId = Seller::where('user_id', Auth::id())->value('id');

        if (Product::where('seller_id', $sellerId)->where('wholesaler_product_id', $wholesalerProduct->id)->exists()) {
            return response()->json(['error' => true, 'message' => labels('wholesaler_labels.already_imported', 'You have already imported this product.')]);
        }

        $name = json_decode($wholesalerProduct->name, true) ?: ['en' => 'Product'];

        $product = Product::create([
            'store_id' => $storeId,
            'category_id' => $wholesalerProduct->category_id,
            'seller_id' => $sellerId,
            'wholesaler_product_id' => $wholesalerProduct->id,
            'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
            'short_description' => json_encode(['en' => Str::limit($name['en'] ?? '', 150)], JSON_UNESCAPED_UNICODE),
            'slug' => generateSlug($name['en'] ?? ('product-' . $wholesalerProduct->id), 'products'),
            // products.image is NOT NULL - the wholesaler UI always requires one for a new listing, but
            // guard here too rather than let an edge-case null 500 the import.
            'image' => $wholesalerProduct->image ?: '',
            'description' => $wholesalerProduct->description,
            'stock_type' => '0', // simple product, stock tracked at the product level
            'stock' => $request->stock,
            'availability' => 1,
            'status' => 1,
            'deliverable_type' => 1, // all
            'deliverable_cities' => '',
            'city_deliverable_type' => 1,
            'minimum_order_quantity' => max(1, (int) $wholesalerProduct->min_order_qty),
            'cod_allowed' => 1,
        ]);

        Product_variants::create([
            'product_id' => $product->id,
            'price' => $request->retail_price,
            'stock' => $request->stock,
            'availability' => 1,
            'status' => 1,
        ]);

        return response()->json(['message' => labels('wholesaler_labels.product_imported', 'Product imported into your catalog successfully.')]);
    }
}
