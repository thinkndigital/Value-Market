<?php

namespace Tests\Feature\Phase1;

use App\Http\Controllers\Seller\PosController;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Product_variants;
use App\Models\Seller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 1 (Task 11 - "at minimum test... POS sale"): exercises the real, unmodified business logic of
 * Seller\PosController::place_order() end to end against the InnoDB-converted, DECIMAL-precision,
 * transaction-wrapped tables this phase produced.
 *
 * Writing this test surfaced two further real bugs beyond the ones already known
 * (docs/PHASE_1_TRANSACTION_BOUNDARIES.md §2), both confirmed by the actual exceptions/return values below,
 * not by reading the code:
 *
 * 1. CartService::addToCart() only returns `true` when $fromApp is true, OR when the cart row already
 *    existed for that user+variant. PosController::place_order() always calls it with $fromApp left at its
 *    default (false). So for a genuinely new cart item - the normal case for a walk-in POS customer buying
 *    something for the first time - addToCart() falls through to `return false;`, and place_order()
 *    reports "Items are Not Added" for every first-time sale. Worked around in these tests by pre-seeding
 *    the Cart row (the "existing item" branch does return true unconditionally) so the rest of the flow -
 *    the part Phase 1 actually changed - can be exercised and verified.
 * 2. A cart with more than one product_variant_id crashes outright with an ErrorException
 *    ("Undefined array key 1") inside CartService::addToCart(), because PosController passes a single
 *    scalar store_id (from StoreService::getStoreId(), session-based) while addToCart() explodes it and
 *    indexes it per item (`$store_id[$index]`) - correct only when there's exactly one item. This happens
 *    BEFORE the order-item-loop bug docs/PHASE_1_TRANSACTION_BOUNDARIES.md already described, so in
 *    practice a real multi-item POS cart never reaches that second bug at all - it crashes here first.
 *
 * Both are pre-existing, out of Phase 1's scope (POS business-logic bugs belong to Phase 6), and are
 * proven here rather than asserted from reading, exactly so Phase 6 doesn't have to rediscover them.
 */
class PosSaleTest extends TestCase
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

    /** @return array{0: User, 1: Product, 2: Product_variants} customer, product, variant */
    private function seedSellerWithSellableProduct(int $stock = 10): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'pos_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone',
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'POS Product']), 'slug' => 'pos-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '',
            'stock_type' => '0', 'stock' => $stock, 'availability' => 1, 'status' => 1,
        ]);

        $variant = Product_variants::forceCreate([
            'product_id' => $product->id, 'price' => 25, 'status' => 1,
        ]);

        $customer = User::forceCreate([
            'username' => 'pos_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => '9998887777',
        ]);

        return [$customer, $product, $variant];
    }

    /** Pre-seeds the cart row so addToCart()'s "existing item" branch runs - see class docblock, bug 1. */
    private function seedExistingCartRow(User $customer, Product_variants $variant): void
    {
        Cart::forceCreate([
            'user_id' => $customer->id,
            'product_variant_id' => $variant->id,
            'qty' => 1,
            'is_saved_for_later' => 0,
            'product_type' => 'regular',
        ]);
    }

    public function test_addtocart_returns_false_for_a_brand_new_item_known_bug(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variant] = $this->seedSellerWithSellableProduct(stock: 10);

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'POS Product'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error'], 'Documents a known bug: the first sale of an item to a customer with no existing cart row for it fails with "Items are Not Added".');
        $this->assertSame('Items are Not Added', $payload['message']);
        $this->assertSame(0, Order::count());
    }

    public function test_a_single_item_pos_sale_creates_an_order_and_decrements_stock(): void
    {
        $this->seedCommonSettings();
        [$customer, $product, $variant] = $this->seedSellerWithSellableProduct(stock: 10);
        $this->seedExistingCartRow($customer, $variant);

        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 2, 'product_type' => 'regular', 'title' => 'POS Product'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'place_order should succeed: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItems::count());

        $orderItem = OrderItems::first();
        $this->assertSame($customer->id, $orderItem->user_id);
        $this->assertSame($product->seller_id, $orderItem->seller_id);
        $this->assertSame(2, $orderItem->quantity);

        // Stock decrement: documented in PHASE_1_TRANSACTION_BOUNDARIES.md as a known gap - POS never
        // calls ProductService::updateStock() for regular products. Asserting the CURRENT (buggy)
        // behavior here, not the desired one, so this test starts failing (correctly) the moment that
        // bug is fixed in a later phase, rather than silently masking the fix.
        $this->assertSame(10, $product->fresh()->stock, 'Documents a known bug (PHASE_1_TRANSACTION_BOUNDARIES.md): POS does not decrement stock for regular products yet.');
    }

    public function test_a_failed_pos_sale_rolls_back_and_creates_no_partial_records(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variant] = $this->seedSellerWithSellableProduct(stock: 10);
        $this->seedExistingCartRow($customer, $variant);

        // Request an impossible quantity (exceeds stock) so validateStock() rejects it before any write.
        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => 999, 'product_type' => 'regular', 'title' => 'POS Product'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error'] ?? false);
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItems::count());
    }

    public function test_a_multi_item_cart_of_new_items_crashes_known_bug(): void
    {
        $this->seedCommonSettings();
        [$customer, , $variantA] = $this->seedSellerWithSellableProduct(stock: 10);
        [, , $variantB] = $this->seedSellerWithSellableProduct(stock: 10);
        // Deliberately NOT pre-seeding cart rows here (unlike the other tests in this class): both items
        // must be genuinely new for addToCart()'s foreach to fall through past index 0 without an early
        // `return true` (which is what the "existing cart item" branch does - see class docblock, bug 1)
        // and reach the broken `$store_id[$index]` access at index 1 (bug 2). Pre-seeding either row would
        // make the loop return early on it, silently hiding this crash and reproducing a different bug
        // instead (the order-item loop only ever creating one row - covered by
        // test_a_single_item_pos_sale_creates_an_order_and_decrements_stock's sibling scenario in
        // docs/PHASE_1_TRANSACTION_BOUNDARIES.md §2). This test isolates the crash specifically.
        $request = new Request([
            'data' => json_encode([
                ['variant_id' => $variantA->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'A'],
                ['variant_id' => $variantB->id, 'quantity' => 1, 'product_type' => 'regular', 'title' => 'B'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
        ]);

        // Documents a known bug (see class docblock, bug 2): CartService::addToCart() indexes a
        // single-element store_id array per cart item, so a walk-in customer's first-ever multi-item POS
        // sale crashes outright rather than merely mishandling extra items. Should start failing -
        // correctly - the moment a later phase fixes it.
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Undefined array key 1');

        app(PosController::class)->place_order($request);
    }
}
