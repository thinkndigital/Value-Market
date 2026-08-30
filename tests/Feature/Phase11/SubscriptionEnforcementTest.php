<?php

namespace Tests\Feature\Phase11;

use App\Http\Controllers\Seller\ProductController;
use App\Models\AffiliateLink;
use App\Models\Category;
use App\Models\CommissionRule;
use App\Models\Currency;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Store;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Follow-up to docs/PHASE_11_SUBSCRIPTIONS.md, which deliberately left commission_rate/max_products
 * unenforced anywhere ("stored, not enforced" was the explicit gap). This closes both: max_products in
 * Seller\ProductController::store() (the same ownership-check scope Phase 2's IDOR fix already applies
 * there - admins creating products on a seller's behalf are unaffected, only the seller's own create flow
 * is checked), and commission_rate as a vendor-scope fallback in AffiliateService::resolveCommissionRule()
 * - only when no explicit admin-managed vendor-scope CommissionRule already exists for that seller.
 */
class SubscriptionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerWithPlan(?SubscriptionPlan $plan, int $storeId): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate([
            'user_id' => $user->id, 'disk' => 'public', 'status' => 1,
            'subscription_plan_id' => $plan?->id,
        ]);
        Store::forceCreate([
            'id' => $storeId, 'name' => json_encode(['en' => 'Store ' . $storeId]), 'slug' => 'store-' . uniqid(),
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $storeId,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'disk' => 'public', 'status' => 1,
        ]);
        Auth::login($user);
        session(['store_id' => $storeId]);

        return [$user, $seller];
    }

    private function makeCategory(): Category
    {
        return Category::forceCreate([
            'name' => json_encode(['en' => 'Category ' . uniqid()]), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);
    }

    private function makeProduct(int $sellerId, int $storeId, int $categoryId): Product
    {
        return Product::forceCreate([
            'category_id' => $categoryId, 'seller_id' => $sellerId, 'store_id' => $storeId,
            'name' => json_encode(['en' => 'Product ' . uniqid()]), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
    }

    public function test_a_seller_at_their_plans_product_limit_is_blocked_from_creating_another(): void
    {
        $category = $this->makeCategory();
        $plan = SubscriptionPlan::forceCreate(['name' => 'Capped', 'slug' => 'capped-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 0, 'max_products' => 1, 'status' => 1]);
        [, $seller] = $this->makeSellerWithPlan($plan, 300);
        $this->makeProduct($seller->id, 300, $category->id);

        $response = app(ProductController::class)->store(new Request([
            'pro_input_name' => 'Second Product', 'short_description' => 'x', 'category_id' => $category->id,
            'pro_input_image' => 'x', 'product_type' => 'simple_product', 'deliverable_type' => 'all',
        ]), true);
        $payload = json_decode($response->getContent(), true);

        $this->assertTrue($payload['error'] ?? false);
        $this->assertStringContainsString('1', $payload['message']);
        $this->assertSame(1, Product::where('seller_id', $seller->id)->count(), 'No second product should have been created.');
    }

    public function test_a_seller_below_their_plans_product_limit_can_still_create_one(): void
    {
        $category = $this->makeCategory();
        $plan = SubscriptionPlan::forceCreate(['name' => 'Roomy', 'slug' => 'roomy-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 0, 'max_products' => 5, 'status' => 1]);
        [, $seller] = $this->makeSellerWithPlan($plan, 301);
        $this->makeProduct($seller->id, 301, $category->id);

        $response = app(ProductController::class)->store(new Request([
            'pro_input_name' => 'Second Product', 'short_description' => 'x', 'category_id' => $category->id,
            'pro_input_image' => 'x', 'product_type' => 'simple_product', 'deliverable_type' => 'all',
        ]), true);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, json_encode($payload));
    }

    public function test_a_seller_with_no_plan_is_never_limited(): void
    {
        $category = $this->makeCategory();
        [, $seller] = $this->makeSellerWithPlan(null, 302);
        for ($i = 0; $i < 3; $i++) {
            $this->makeProduct($seller->id, 302, $category->id);
        }

        $response = app(ProductController::class)->store(new Request([
            'pro_input_name' => 'Yet Another Product', 'short_description' => 'x', 'category_id' => $category->id,
            'pro_input_image' => 'x', 'product_type' => 'simple_product', 'deliverable_type' => 'all',
        ]), true);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, json_encode($payload));
    }

    public function test_a_plan_with_no_max_products_set_is_unlimited(): void
    {
        $category = $this->makeCategory();
        $plan = SubscriptionPlan::forceCreate(['name' => 'Unlimited', 'slug' => 'unlimited-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 0, 'max_products' => null, 'status' => 1]);
        [, $seller] = $this->makeSellerWithPlan($plan, 303);
        for ($i = 0; $i < 3; $i++) {
            $this->makeProduct($seller->id, 303, $category->id);
        }

        $response = app(ProductController::class)->store(new Request([
            'pro_input_name' => 'Another Product', 'short_description' => 'x', 'category_id' => $category->id,
            'pro_input_image' => 'x', 'product_type' => 'simple_product', 'deliverable_type' => 'all',
        ]), true);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, json_encode($payload));
    }

    public function test_a_sellers_plan_commission_rate_is_used_when_no_explicit_vendor_rule_exists(): void
    {
        $sellerUser = User::forceCreate([
            'username' => 'aff_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $plan = SubscriptionPlan::forceCreate(['name' => 'LowFee', 'slug' => 'lowfee-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 0, 'commission_rate' => 3.5, 'status' => 1]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1, 'subscription_plan_id' => $plan->id]);

        $affiliateUser = User::forceCreate([
            'username' => 'affiliate_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $link = app(AffiliateService::class)->createLink($affiliateUser->id, AffiliateLink::TARGET_PLATFORM);

        $rule = app(AffiliateService::class)->resolveCommissionRule($link, null, null, $seller->id);

        $this->assertNotNull($rule);
        $this->assertSame('percentage', $rule->rate_type);
        $this->assertSame(3.5, (float) $rule->rate_value);
    }

    public function test_an_explicit_admin_vendor_rule_still_wins_over_the_plans_commission_rate(): void
    {
        $sellerUser = User::forceCreate([
            'username' => 'aff_seller2_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $plan = SubscriptionPlan::forceCreate(['name' => 'LowFee2', 'slug' => 'lowfee2-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 0, 'commission_rate' => 3.5, 'status' => 1]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1, 'subscription_plan_id' => $plan->id]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_VENDOR, 'scope_id' => $seller->id, 'rate_type' => 'percentage', 'rate_value' => 9, 'status' => CommissionRule::STATUS_ACTIVE]);

        $affiliateUser = User::forceCreate([
            'username' => 'affiliate2_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $link = app(AffiliateService::class)->createLink($affiliateUser->id, AffiliateLink::TARGET_PLATFORM);

        $rule = app(AffiliateService::class)->resolveCommissionRule($link, null, null, $seller->id);

        $this->assertNotNull($rule);
        $this->assertSame(9.0, (float) $rule->rate_value, 'The explicit admin-managed vendor rule must win over the plan default.');
    }

    public function test_the_my_subscription_page_renders_the_sellers_current_plan(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        \App\Models\Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);

        $plan = SubscriptionPlan::forceCreate(['name' => 'Visible Plan', 'slug' => 'visible-' . uniqid(), 'billing_cycle' => 'monthly', 'price' => 10, 'max_products' => 20, 'status' => 1]);
        [$user] = $this->makeSellerWithPlan($plan, 304);

        $response = $this->actingAs($user)->get(route('seller.my_subscription.index'));

        $response->assertOk();
        $response->assertSee('Visible Plan');
    }

    public function test_a_seller_with_no_plan_commission_rate_falls_through_to_platform(): void
    {
        $sellerUser = User::forceCreate([
            'username' => 'aff_seller3_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);
        CommissionRule::forceCreate(['scope' => CommissionRule::SCOPE_PLATFORM, 'scope_id' => null, 'rate_type' => 'percentage', 'rate_value' => 2, 'status' => CommissionRule::STATUS_ACTIVE]);

        $affiliateUser = User::forceCreate([
            'username' => 'affiliate3_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER,
        ]);
        $link = app(AffiliateService::class)->createLink($affiliateUser->id, AffiliateLink::TARGET_PLATFORM);

        $rule = app(AffiliateService::class)->resolveCommissionRule($link, null, null, $seller->id);

        $this->assertNotNull($rule);
        $this->assertSame(CommissionRule::SCOPE_PLATFORM, $rule->scope);
        $this->assertSame(2.0, (float) $rule->rate_value);
    }
}
