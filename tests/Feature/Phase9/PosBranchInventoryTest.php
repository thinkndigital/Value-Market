<?php

namespace Tests\Feature\Phase9;

use App\Http\Controllers\Seller\PosController;
use App\Models\Branch;
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
use App\Models\StockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * 32-phase SaaS brief, Phase 9/10 (docs/PHASE_9_10_POS_CONCURRENCY_AND_BRANCHES.md): before this,
 * PosController::place_order()'s stock check (validateStock()) only ever compared against the seller's
 * total global stock, never against what a specific branch actually has on hand in stock_items (tracked
 * since this repo's own Phase 5, but never enforced against). A seller with Branch A holding 6 units and
 * Branch B holding 4 could sell 8 from Branch A's POS and it would succeed. Fixed via
 * InventoryService::validateBranchStock(), called before any write - same "check then abort" placement as
 * the existing global validateStock() check right above it in PosController::place_order().
 */
class PosBranchInventoryTest extends TestCase
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

    private const TEST_STORE_ID = 200;

    /** @return array{0: User, 1: Seller, 2: Product, 3: Product_variants, 4: Branch} */
    private function seedSellerProductAndBranch(int $globalStock = 10): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'pos_branch_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
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
            'name' => json_encode(['en' => 'Branch Product']), 'slug' => 'branch-product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'stock_type' => '2', 'availability' => 1, 'status' => 1,
        ]);
        $variant = Product_variants::forceCreate(['product_id' => $product->id, 'price' => 20, 'status' => 1, 'stock' => $globalStock, 'availability' => 1]);
        $branch = Branch::forceCreate(['seller_id' => $seller->id, 'name' => 'Downtown', 'status' => Branch::STATUS_ACTIVE]);

        return [$sellerUser, $seller, $product, $variant, $branch];
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'username' => 'pos_branch_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);
    }

    private function placeOrderRequest(User $customer, Product_variants $variant, int $qty, ?int $branchId): Request
    {
        return new Request([
            'data' => json_encode([
                ['variant_id' => $variant->id, 'quantity' => $qty, 'product_type' => 'regular', 'title' => 'Branch Product'],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'delivery_charges' => 0,
            'discount' => 0,
            'pos_branch_id' => $branchId,
        ]);
    }

    public function test_a_sale_with_no_branch_specified_is_unaffected_by_this_change(): void
    {
        $this->seedCommonSettings();
        [, , , $variant] = $this->seedSellerProductAndBranch(globalStock: 5);
        $customer = $this->makeCustomer();
        // Deliberately no stock_items row for any branch - the old, pre-branch-feature behavior.

        $response = app(PosController::class)->place_order($this->placeOrderRequest($customer, $variant, 2, null));
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'A sale with no branch context must behave exactly as before: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
    }

    public function test_a_sale_within_the_branchs_own_on_hand_stock_succeeds(): void
    {
        $this->seedCommonSettings();
        [, $seller, , $variant, $branch] = $this->seedSellerProductAndBranch(globalStock: 10);
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $customer = $this->makeCustomer();

        $response = app(PosController::class)->place_order($this->placeOrderRequest($customer, $variant, 3, $branch->id));
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'Selling within branch stock should succeed: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
        $this->assertSame(1, OrderItems::count());
    }

    public function test_a_sale_exceeding_the_branchs_on_hand_stock_is_rejected_even_though_global_stock_is_sufficient(): void
    {
        $this->seedCommonSettings();
        // Global stock is 10 - plenty overall - but this specific branch only has 2 on hand.
        [, $seller, , $variant, $branch] = $this->seedSellerProductAndBranch(globalStock: 10);
        StockItem::forceCreate(['seller_id' => $seller->id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 2]);
        $customer = $this->makeCustomer();

        $response = app(PosController::class)->place_order($this->placeOrderRequest($customer, $variant, 8, $branch->id));
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error'] ?? false, 'Selling more than this branch has on hand must be rejected: ' . json_encode($payload));
        $this->assertSame(0, Order::count(), 'No order should be created for a rejected branch-stock sale.');
        $this->assertSame(10, $variant->fresh()->stock, 'Global stock must be untouched by the rejected sale.');
    }

    public function test_a_branch_with_no_stock_items_row_yet_is_treated_as_zero_on_hand(): void
    {
        $this->seedCommonSettings();
        [, , , $variant, $branch] = $this->seedSellerProductAndBranch(globalStock: 10);
        // No StockItem row for this branch at all - never received anything into it yet.
        $customer = $this->makeCustomer();

        $response = app(PosController::class)->place_order($this->placeOrderRequest($customer, $variant, 1, $branch->id));
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error'] ?? false);
        $this->assertSame(0, Order::count());
    }

    public function test_a_branch_belonging_to_another_seller_is_ignored_not_trusted(): void
    {
        $this->seedCommonSettings();
        [, , , $variant] = $this->seedSellerProductAndBranch(globalStock: 5);
        $otherSellerUser = User::forceCreate([
            'username' => 'other_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $otherSeller = Seller::forceCreate(['user_id' => $otherSellerUser->id, 'disk' => 'public', 'status' => 1]);
        $otherBranch = Branch::forceCreate(['seller_id' => $otherSeller->id, 'name' => 'Not Yours', 'status' => Branch::STATUS_ACTIVE]);
        $customer = $this->makeCustomer();

        // resolveOwnedPosBranchId() already rejects an unowned branch id (falls back to null) - proving
        // this still holds after adding the branch-stock check on top of it, using the acting seller's
        // own global stock instead of a stranger's branch.
        $response = app(PosController::class)->place_order($this->placeOrderRequest($customer, $variant, 2, $otherBranch->id));
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'An unowned branch id must be ignored, not trusted: ' . json_encode($payload));
        $this->assertSame(1, Order::count());
    }
}
