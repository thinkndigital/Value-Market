<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Product_variants;
use App\Models\WholesaleOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fulfillment logic for a wholesale order (docs/WHOLESALER_MODULE.md v2) - the wholesaler-side "mark
 * delivered" action calls this, not the seller directly. Extracted from v1's
 * Seller\WholesalerMarketplaceController::import() (which used to do this the moment a seller clicked
 * Import, with no order record at all) - same product-creation logic, just triggered by fulfillment instead.
 */
class WholesaleOrderService
{
    /**
     * Creates the seller's own Product (first order for this listing) or tops up its stock (a repeat
     * order), and marks the order delivered. Idempotent: a second call on an already-fulfilled order is a
     * no-op, so a retried request can never double-create a product or double-add stock.
     */
    public function fulfill(WholesaleOrder $order): Product
    {
        if ($order->fulfilled_product_id) {
            return $order->fulfilledProduct;
        }

        return DB::transaction(function () use ($order) {
            $wholesalerProduct = $order->wholesalerProduct;
            $existing = Product::where('seller_id', $order->seller_id)
                ->where('wholesaler_product_id', $order->wholesaler_product_id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->increment('stock', $order->quantity);
                $variant = Product_variants::where('product_id', $existing->id)->first();
                if ($variant) {
                    $variant->increment('stock', $order->quantity);
                }
                $product = $existing;
            } else {
                $name = json_decode($wholesalerProduct->name, true) ?: ['en' => 'Product'];

                $product = Product::create([
                    'store_id' => $order->store_id,
                    'category_id' => $wholesalerProduct->category_id,
                    'seller_id' => $order->seller_id,
                    'wholesaler_product_id' => $wholesalerProduct->id,
                    'name' => json_encode($name, JSON_UNESCAPED_UNICODE),
                    'short_description' => json_encode(['en' => Str::limit($name['en'] ?? '', 150)], JSON_UNESCAPED_UNICODE),
                    'slug' => generateSlug($name['en'] ?? ('product-' . $wholesalerProduct->id), 'products'),
                    'image' => $wholesalerProduct->image ?: '',
                    'description' => $wholesalerProduct->description,
                    'stock_type' => '0',
                    'stock' => $order->quantity,
                    'availability' => 1,
                    'status' => 1,
                    'deliverable_type' => 1,
                    'deliverable_cities' => '',
                    'city_deliverable_type' => 1,
                    'minimum_order_quantity' => max(1, (int) $wholesalerProduct->min_order_qty),
                    'cod_allowed' => 1,
                ]);

                Product_variants::create([
                    'product_id' => $product->id,
                    'price' => $order->retail_price,
                    'stock' => $order->quantity,
                    'availability' => 1,
                    'status' => 1,
                ]);
            }

            $order->status = WholesaleOrder::STATUS_DELIVERED;
            $order->fulfilled_product_id = $product->id;
            $order->save();

            return $product;
        });
    }
}
