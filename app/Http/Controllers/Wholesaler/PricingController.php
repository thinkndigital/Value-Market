<?php

namespace App\Http\Controllers\Wholesaler;

use App\Models\WholesaleOrder;
use App\Models\Wholesaler;
use App\Models\WholesalerProduct;
use App\Models\WholesalerProductPriceTier;
use App\Traits\HandlesValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Master architecture prompt Phase 6 (section 18 "Wholesale" group): lets a wholesaler define
 * quantity-break pricing on their own listings, generic (open to every seller) or seller-specific -
 * see WholesalerProduct::priceFor() for how a tier is picked at order time.
 */
class PricingController extends Controller
{
    use HandlesValidation;

    private function currentWholesaler(): Wholesaler
    {
        return Wholesaler::where('user_id', Auth::id())->firstOrFail();
    }

    private function ownedProduct(int $productId): ?WholesalerProduct
    {
        return WholesalerProduct::where('wholesaler_id', $this->currentWholesaler()->id)->find($productId);
    }

    public function index()
    {
        $wholesaler = $this->currentWholesaler();
        $products = WholesalerProduct::where('wholesaler_id', $wholesaler->id)->orderByDesc('id')->get();

        // Only sellers who have actually ordered from this wholesaler are offered for a seller-specific
        // tier - matches ClientController's "My Buyers" scope, avoids a free-text seller id field.
        $knownSellers = WholesaleOrder::where('wholesaler_id', $wholesaler->id)
            ->with('seller.user')
            ->get()
            ->unique('seller_id')
            ->map(fn ($o) => [
                'id' => $o->seller_id,
                'name' => optional(optional($o->seller)->user)->username ?? ('#' . $o->seller_id),
            ])
            ->values();

        return view('wholesaler.pages.views.pricing.index', compact('products', 'knownSellers'));
    }

    public function tiersList(Request $request, $productId)
    {
        $product = $this->ownedProduct((int) $productId);
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $tiers = $product->priceTiers()->with('seller.user')->orderBy('min_quantity')->get();

        $rows = $tiers->map(function ($t) {
            return [
                'id' => $t->id,
                'seller' => $t->seller_id ? (optional(optional($t->seller)->user)->username ?? ('#' . $t->seller_id)) : labels('wholesaler_labels.all_sellers', 'All Sellers'),
                'min_quantity' => $t->min_quantity,
                'unit_price' => $t->unit_price,
                'operate' => '<button type="button" class="btn btn-sm btn-danger delete-price-tier" data-id="' . $t->id . '"><i class="bx bx-trash"></i></button>',
            ];
        });

        return response()->json(['rows' => $rows, 'total' => $tiers->count()]);
    }

    public function store(Request $request, $productId)
    {
        $product = $this->ownedProduct((int) $productId);
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $rules = [
            'min_quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'seller_id' => 'nullable|integer|exists:seller_data,id',
        ];
        if ($response = $this->HandlesValidation($request, $rules)) {
            return $response;
        }

        WholesalerProductPriceTier::create([
            'wholesaler_product_id' => $product->id,
            'seller_id' => $request->seller_id ?: null,
            'min_quantity' => $request->min_quantity,
            'unit_price' => $request->unit_price,
        ]);

        return response()->json(['message' => labels('wholesaler_labels.price_tier_added', 'Pricing tier added successfully.')]);
    }

    public function destroy($productId, $tierId)
    {
        $product = $this->ownedProduct((int) $productId);
        if (!$product) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $tier = WholesalerProductPriceTier::where('wholesaler_product_id', $product->id)->find($tierId);
        if (!$tier) {
            return response()->json(['error' => true, 'message' => labels('seller.data_not_found', 'Data Not Found')], 404);
        }

        $tier->delete();

        return response()->json(['message' => labels('wholesaler_labels.price_tier_deleted', 'Pricing tier deleted.')]);
    }
}
