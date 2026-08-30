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
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 1 (Task 11 - "at minimum test... POS sale"): exercises the real, unmodified business logic of
 * Seller\PosController::place_order() end to end against the InnoDB-converted, DECIMAL-precision,
 * transaction-wrapped tables this phase produced.
 *
 * Writing this test originally surfaced two further real bugs beyond the ones already known
 * (docs/PHASE_1_TRANSACTION_BOUNDARIES.md §2), confirmed by the actual exceptions/return values at the
 * time, not by reading the code:
 *
 * 1. CartService::addToCart() only returned `true` when $fromApp was true, OR when the cart row already
 *    existed for that user+variant - and it `return`ed from *inside* its loop the moment either of those
 *    happened, so even a correctly-updated existing item stopped the whole batch from processing any further
 *    items. PosController::place_order() always calls it with $fromApp left at its default (false), so a
 *    genuinely new cart item - the normal case for a walk-in POS customer buying something for the first
 *    time - fell through to `return false;`, reporting "Items are Not Added" for every first-time sale.
 * 2. A cart with more than one product_variant_id crashed outright with an ErrorException ("Undefined array
 *    key 1") inside the same method, because PosController passes a single scalar store_id (from
 *    StoreService::getStoreId(), session-based) while addToCart() exploded it and indexed it per item
 *    (`$store_id[$index]`) - correct only when there's exactly one item.
 *
 * Both fixed now (docs/PHASE_9_POS_CART_FIX.md): addToCart() processes every item in the batch before
 * returning once, and a store_id index that doesn't exist falls back to the first (shared) value instead of
 * crashing. `test_a_brand_new_items_first_pos_sale_succeeds`/`test_a_multi_item_cart_of_new_items_succeeds`
 * below now assert the corrected behavior instead of documenting the bugs.
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

    /** Shared across every seller this file creates - see seedSellerWithSellableProduct()'s docblock. */
    private const TEST_STORE_ID = 100;

    /**
     * @return array{0: User, 1: Product, 2: Product_variants} customer, product, variant
     *
     * Security fix (docs/SECURITY_AUDIT.md §6.2): PosController::place_order()/combo_place_order() now
     * verify the acting seller actually manages the store_id their session resolves to
     * (TenantContext::verifiedSellerStoreId()) - previously neither method checked Auth::user() at all, the
     * exact gap that fix closes. This helper logs the seller in and gives them a real, owned store (a fixed
     * store_id shared across every seller this test file creates - verifiedSellerStoreId() only checks
     * "does the currently authenticated user manage this store_id", not product ownership, so a shared
     * store_id is fine here and keeps every existing test scenario, none of which are about store
     * ownership, working unchanged).
     */
    private function seedSellerWithSellableProduct(int $stock = 10): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'pos_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => self::TEST_STORE_ID,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($sellerUser);
        session(['store_id' => self::TEST_STORE_ID]);

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

    public function test_a_brand_new_items_first_pos_sale_succeeds(): void
    {
        $this->seedCommonSettings();
        [$customer, $product, $variant] = $this->seedSellerWithSellableProduct(stock: 10);
        // Deliberately no pre-seeded Cart row - this is the walk-in-customer, first-time-buying-this-item
        // case CartService::addToCart() used to fail (see class docblock, bug 1).

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

        $this->assertFalse($payload['error'] ?? true, 'place_order should succeed: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItems::count());
        $this->assertSame(9, $product->fresh()->stock);
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

        // Stock decrement: this used to be a documented gap (PHASE_1_TRANSACTION_BOUNDARIES.md) - POS never
        // called ProductService::updateStock() for regular products. Fixed in Phase 6
        // (docs/PHASE_6_POS.md) - this now asserts the correct decremented value instead of the old bug.
        $this->assertSame(8, $product->fresh()->stock);
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

    public function test_a_multi_item_cart_of_new_items_succeeds(): void
    {
        $this->seedCommonSettings();
        [$customer, $productA, $variantA] = $this->seedSellerWithSellableProduct(stock: 10);
        [, $productB, $variantB] = $this->seedSellerWithSellableProduct(stock: 10);
        // Deliberately no pre-seeded cart rows - both items are genuinely new, the exact walk-in-customer
        // scenario that used to crash CartService::addToCart() past the first item (see class docblock).

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

        $response = app(PosController::class)->place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'place_order should succeed: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
        $this->assertSame(2, OrderItems::count(), 'Both new items must be recorded, not just the first.');
        $this->assertSame(9, $productA->fresh()->stock);
        $this->assertSame(9, $productB->fresh()->stock);
    }
}
