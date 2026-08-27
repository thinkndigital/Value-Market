<?php

namespace Tests\Feature\Phase6;

use App\Http\Controllers\Seller\PosController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;
use App\Models\OrderItems;
use App\Models\PosPayment;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 6 (docs/PHASE_6_POS.md): PosController::place_order()'s per-item loop used to build one OrderItems
 * row, DB::commit(), and return - all inside the loop body - so a cart with more than one line item only
 * ever recorded its first item (docs/PHASE_1_TRANSACTION_BOUNDARIES.md flagged this and deferred the fix to
 * this phase). Stock was also never decremented for regular products. This proves both are fixed together,
 * for a real multi-item cart - not just each in isolation.
 */
class PosMultiItemAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    private function seedCommonSettings(): void
    {
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Setting::forceCreate([
            'variable' => 'system_settings',
            'value' => json_encode(['single_seller_order_system' => '0']),
        ]);
    }

    /** @return array{0: User, 1: Seller, 2: array<int, Product_variants>} */
    private function seedSellerWithTwoVariants(int $stockEach = 10): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'pos_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone',
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);

        $variants = [];
        foreach (['A', 'B'] as $label) {
            $product = Product::forceCreate([
                'category_id' => $category->id, 'seller_id' => $seller->id,
                'name' => json_encode(['en' => "POS Product $label"]), 'slug' => 'pos-product-' . strtolower($label) . '-' . uniqid(),
                'image' => '', 'deliverable_cities' => '',
                'stock_type' => '2', 'availability' => 1, 'status' => 1,
            ]);
            $variants[] = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 25, 'status' => 1, 'stock' => $stockEach, 'availability' => 1]);
        }

        $customer = User::forceCreate([
            'username' => 'pos_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);

        return [$customer, $seller, $variants];
    }

    public function test_a_two_item_pos_cart_records_both_items_and_decrements_both_stocks(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variants] = $this->seedSellerWithTwoVariants(stockEach: 10);
        [$variantA, $variantB] = $variants;

        // Pre-seed cart rows for both items - CartService::addToCart() has its own separate, pre-existing
        // bug crashing a genuinely-new multi-item cart (documented in Phase1\PosSaleTest's class docblock,
        // "bug 2") which is not part of this phase's scope (a different service/file); pre-seeding sidesteps
        // it the same way Phase 1's own POS tests already do, to isolate what THIS phase actually fixed.
        foreach ([$variantA, $variantB] as $variant) {
            Cart::forceCreate([
                'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
                'is_saved_for_later' => 0, 'product_type' => 'regular',
            ]);
        }

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variantA->id, 'quantity' => 2, 'product_type' => 'regular', 'title' => 'A'],
                ['variant_id' => $variantB->id, 'quantity' => 3, 'product_type' => 'regular', 'title' => 'B'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'place_order should succeed: ' . json_encode($payload));
        $this->assertSame(2, OrderItems::count(), 'both cart items should have created an order item - not just the first');

        $this->assertSame(8, $variantA->fresh()->stock);
        $this->assertSame(7, $variantB->fresh()->stock);

        $this->assertSame(1, StockMovement::where('product_variant_id', $variantA->id)->count());
        $this->assertSame(1, StockMovement::where('product_variant_id', $variantB->id)->count());
    }

    public function test_a_pos_sale_with_no_explicit_payments_records_a_single_payment_line(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variants] = $this->seedSellerWithTwoVariants(stockEach: 10);
        $variant = $variants[0];
        Cart::forceCreate([
            'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
            'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'A'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);
        $this->assertFalse($payload['error'] ?? true, json_encode($payload));

        $payments = PosPayment::where('order_id', $payload['order_id'])->get();
        $this->assertCount(1, $payments);
        $this->assertSame('cash', $payments->first()->payment_method);
    }

    public function test_a_pos_sale_with_explicit_split_payments_records_each_line(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variants] = $this->seedSellerWithTwoVariants(stockEach: 10);
        $variant = $variants[0];
        Cart::forceCreate([
            'user_id' => $customer->id, 'product_variant_id' => $variant->id, 'qty' => 1,
            'is_saved_for_later' => 0, 'product_type' => 'regular',
        ]);

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'A'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 15],
                ['payment_method' => 'card', 'amount' => 10],
            ],
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);
        $this->assertFalse($payload['error'] ?? true, json_encode($payload));

        $payments = PosPayment::where('order_id', $payload['order_id'])->get();
        $this->assertCount(2, $payments);
        $this->assertSame(25.0, (float) $payments->sum('amount'));
    }
}
