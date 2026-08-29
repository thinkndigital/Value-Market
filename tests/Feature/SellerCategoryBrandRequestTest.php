<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\CategoryController as SellerCategoryController;
use App\Http\Controllers\Seller\ProductController as SellerProductController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * docs/CHANGELOG_FEATURE_AUDIT.md v1.0.6 + v1.0.11: "Sellers can request custom categories/brands", "Admin
 * can approve/reject seller category/brand requests", "Approved categories/brands become available to
 * sellers for product listing", "Seller App can view/delete pending Categories/Brands".
 *
 * Covers the full lifecycle end-to-end: a seller submits a request (Seller\CategoryController::store()/
 * Seller\BrandController::store(), which already existed but never tracked *who* requested a row); a
 * duplicate pending request is rejected; a seller cannot see or delete another seller's pending request
 * (IDOR, mirroring tests/Feature/SellerUpdateComboProductTest.php's and Phase 3's ownership-scoping
 * pattern); admin approving a request flips status to 1 (making it usable via the exact same
 * `where('status', 1)` queries every other admin/seller category+brand endpoint already uses) and, for
 * categories specifically, grants it to the requesting seller's product-form category widget; admin
 * rejecting a request leaves status at 0 but keeps the row visible (as "Rejected") in the seller's own
 * list, and the seller cannot then delete it (pending-only delete guard).
 */
class SellerCategoryBrandRequestTest extends TestCase
{
    use RefreshDatabase;

    private function shareBaseViewData(): void
    {
        Currency::forceCreate(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_default' => 1, 'status' => 1]);
        Setting::forceCreate(['variable' => 'system_settings', 'value' => json_encode(['app_name' => 'Value Market', 'favicon' => ''])]);
        Store::forceCreate([
            'id' => 1, 'name' => json_encode(['en' => 'Main Store']), 'slug' => 'main-store',
            'disk' => 'public', 'background_color' => '#ffffff', 'status' => 1,
        ]);

        $currencyDetails = app(\App\Services\CurrencyService::class)->getDefaultCurrency();
        view()->share([
            'currency_symbol' => $currencyDetails->symbol ?? '', 'currency_code' => $currencyDetails->code ?? '',
            'system_settings' => ['app_name' => 'Value Market', 'favicon' => ''], 'web_settings' => [], 'version' => 1,
        ]);
    }

    /** @return array{0: User, 1: Seller} */
    private function makeSeller(string $name): array
    {
        $user = User::forceCreate([
            'username' => $name . '_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER, 'active' => 1,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => 1, 'status' => 1,
            'slug' => 'store-' . uniqid(), 'store_name' => $name . ' Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'category_ids' => '', 'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$user, $seller];
    }

    private function makeAdmin(): User
    {
        return User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
    }

    // ---------------------------------------------------------------------------------------------
    // Categories
    // ---------------------------------------------------------------------------------------------

    public function test_seller_can_submit_a_category_request(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller] = $this->makeSeller('cat-seller-a');

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->post('/categories', [
            'name' => 'Requested Category ' . uniqid(),
            'category_image' => 'categories/img.png',
            'banner' => 'categories/banner.png',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayNotHasKey('error', $data);

        $category = Category::where('requested_by_seller_id', $seller->id)->first();
        $this->assertNotNull($category);
        $this->assertEquals(2, $category->status);
        $this->assertEquals(Category::APPROVAL_PENDING, $category->approval_status);
    }

    public function test_duplicate_pending_category_request_is_rejected(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller] = $this->makeSeller('cat-seller-dup');
        $name = 'Duplicate Category ' . uniqid();

        Category::forceCreate([
            'name' => json_encode(['en' => $name]), 'slug' => 'dup-' . uniqid(),
            'image' => 'x.png', 'banner' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Category::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->post('/categories', [
            'name' => $name,
            'category_image' => 'categories/img2.png',
            'banner' => 'categories/banner2.png',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $data = $response->json();
        $this->assertTrue($data['error']);
        $this->assertEquals(1, Category::where('name->en', $name)->count());
    }

    public function test_seller_cannot_see_or_delete_another_sellers_pending_category_request(): void
    {
        $this->shareBaseViewData();
        [, $sellerA] = $this->makeSeller('cat-seller-victim');
        [$sellerBUser,] = $this->makeSeller('cat-seller-attacker');

        $victimCategory = Category::forceCreate([
            'name' => json_encode(['en' => 'Victim Category ' . uniqid()]), 'slug' => 'victim-' . uniqid(),
            'image' => 'x.png', 'banner' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $sellerA->id, 'approval_status' => Category::APPROVAL_PENDING,
        ]);

        // Cannot see it in their own list.
        $listResponse = $this->actingAs($sellerBUser)->withSession(['store_id' => 1])
            ->get(route('seller_categories.list'));
        $ids = collect($listResponse->json('rows'))->pluck('id')->all();
        $this->assertNotContains($victimCategory->id, $ids);

        // Cannot delete it either.
        $deleteResponse = $this->actingAs($sellerBUser)->withSession(['store_id' => 1])
            ->get(route('seller.categories.destroy', $victimCategory->id));
        $deleteData = $deleteResponse->json();
        $this->assertNotEmpty($deleteData['error'] ?? null);
        $this->assertDatabaseHas('categories', ['id' => $victimCategory->id]);
    }

    public function test_admin_can_approve_a_category_request_and_it_becomes_usable_for_the_seller(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller] = $this->makeSeller('cat-seller-approve');
        $admin = $this->makeAdmin();

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Approvable Category ' . uniqid()]), 'slug' => 'approve-' . uniqid(),
            'image' => 'x.png', 'banner' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Category::APPROVAL_PENDING,
        ]);

        // Route literally registered as admin/admin/categories/update_status/{id} - the original route
        // string is 'admin/categories/update_status/{id}' inside a group already prefixed 'admin/'
        // (pre-existing, confirmed via `php artisan route:list`), unrelated to this feature.
        $response = $this->actingAs($admin)->get('/admin/admin/categories/update_status/' . $category->id . '?status=1');
        $response->assertOk();
        $this->assertArrayNotHasKey('status_error', $response->json());

        $category->refresh();
        $this->assertEquals(1, $category->status);
        $this->assertEquals(Category::APPROVAL_APPROVED, $category->approval_status);

        // Granted to the requesting seller's seller_store.category_ids pivot.
        $sellerStore = SellerStore::where('seller_id', $seller->id)->where('store_id', 1)->first();
        $this->assertContains((string) $category->id, explode(',', $sellerStore->category_ids));

        // Now visible via the exact endpoint the seller's product-add form category dropdown already uses.
        Auth::login($sellerUser);
        session(['store_id' => 1]);
        $result = app(SellerCategoryController::class)->getSellerCategories(new Request(['seller_id' => $seller->id]));
        $categoryIds = collect($result)->pluck('id')->all();
        $this->assertContains($category->id, $categoryIds);
    }

    public function test_admin_can_reject_a_category_request_seller_still_sees_it_but_cannot_delete(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller] = $this->makeSeller('cat-seller-reject');
        $admin = $this->makeAdmin();

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Rejectable Category ' . uniqid()]), 'slug' => 'reject-' . uniqid(),
            'image' => 'x.png', 'banner' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Category::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/admin/admin/categories/update_status/' . $category->id . '?status=0');
        $response->assertOk();

        $category->refresh();
        $this->assertEquals(0, $category->status);
        $this->assertEquals(Category::APPROVAL_REJECTED, $category->approval_status);

        // Still visible to the seller in their own list (not deleted).
        $listResponse = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller_categories.list'));
        $ids = collect($listResponse->json('rows'))->pluck('id')->all();
        $this->assertContains($category->id, $ids);

        // But cannot delete a rejected (non-pending) request.
        $deleteResponse = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller.categories.destroy', $category->id));
        $deleteData = $deleteResponse->json();
        $this->assertNotEmpty($deleteData['error'] ?? null);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_seller_can_delete_their_own_still_pending_category_request(): void
    {
        [$sellerUser, $seller] = $this->makeSeller('cat-seller-withdraw');

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Withdrawable Category ' . uniqid()]), 'slug' => 'withdraw-' . uniqid(),
            'image' => 'x.png', 'banner' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Category::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller.categories.destroy', $category->id));
        $data = $response->json();

        $this->assertFalse($data['error'] ?? true);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    // ---------------------------------------------------------------------------------------------
    // Brands
    // ---------------------------------------------------------------------------------------------

    public function test_seller_can_submit_a_brand_request(): void
    {
        [$sellerUser, $seller] = $this->makeSeller('brand-seller-a');

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->post('/brands', [
            'brand_name' => 'Requested Brand ' . uniqid(),
            'image' => 'brands/img.png',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $data = $response->json();
        $this->assertArrayNotHasKey('error', $data);

        $brand = Brand::where('requested_by_seller_id', $seller->id)->first();
        $this->assertNotNull($brand);
        $this->assertEquals(2, $brand->status);
        $this->assertEquals(Brand::APPROVAL_PENDING, $brand->approval_status);
    }

    public function test_duplicate_pending_brand_request_is_rejected(): void
    {
        [$sellerUser, $seller] = $this->makeSeller('brand-seller-dup');
        $name = 'Duplicate Brand ' . uniqid();

        Brand::forceCreate([
            'name' => json_encode(['en' => $name]), 'slug' => 'dup-' . uniqid(),
            'image' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Brand::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])->post('/brands', [
            'brand_name' => $name,
            'image' => 'brands/img2.png',
        ], ['X-Requested-With' => 'XMLHttpRequest']);

        $data = $response->json();
        $this->assertTrue($data['error']);
        $this->assertEquals(1, Brand::where('name->en', $name)->count());
    }

    public function test_seller_cannot_see_or_delete_another_sellers_pending_brand_request(): void
    {
        [, $sellerA] = $this->makeSeller('brand-seller-victim');
        [$sellerBUser,] = $this->makeSeller('brand-seller-attacker');

        $victimBrand = Brand::forceCreate([
            'name' => json_encode(['en' => 'Victim Brand ' . uniqid()]), 'slug' => 'victim-' . uniqid(),
            'image' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $sellerA->id, 'approval_status' => Brand::APPROVAL_PENDING,
        ]);

        $listResponse = $this->actingAs($sellerBUser)->withSession(['store_id' => 1])
            ->get(route('seller.brands.list'));
        $ids = collect($listResponse->json('rows'))->pluck('id')->all();
        $this->assertNotContains($victimBrand->id, $ids);

        $deleteResponse = $this->actingAs($sellerBUser)->withSession(['store_id' => 1])
            ->get(route('seller.brands.destroy', $victimBrand->id));
        $deleteData = $deleteResponse->json();
        $this->assertNotEmpty($deleteData['error'] ?? null);
        $this->assertDatabaseHas('brands', ['id' => $victimBrand->id]);
    }

    public function test_admin_can_approve_a_brand_request_and_it_becomes_usable_for_product_listing(): void
    {
        $this->shareBaseViewData();
        [, $seller] = $this->makeSeller('brand-seller-approve');
        $admin = $this->makeAdmin();

        $brand = Brand::forceCreate([
            'name' => json_encode(['en' => 'Approvable Brand ' . uniqid()]), 'slug' => 'approve-' . uniqid(),
            'image' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Brand::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/admin/brand/update_status/' . $brand->id . '?status=1');
        $response->assertOk();
        $this->assertArrayNotHasKey('status_error', $response->json());

        $brand->refresh();
        $this->assertEquals(1, $brand->status);
        $this->assertEquals(Brand::APPROVAL_APPROVED, $brand->approval_status);

        // Now visible via the exact endpoint the seller's product-add form brand dropdown already uses
        // (Seller\ProductController::getBrands(), filtered only by store_id + status==1).
        [$anySellerUser,] = $this->makeSeller('brand-seller-consumer');
        Auth::login($anySellerUser);
        session(['store_id' => 1]);
        $result = app(SellerProductController::class)->getBrands(new Request(['search' => '']));
        $brandIds = collect($result)->pluck('id')->all();
        $this->assertContains($brand->id, $brandIds);
    }

    public function test_admin_can_reject_a_brand_request_seller_still_sees_it_but_cannot_delete(): void
    {
        $this->shareBaseViewData();
        [$sellerUser, $seller] = $this->makeSeller('brand-seller-reject');
        $admin = $this->makeAdmin();

        $brand = Brand::forceCreate([
            'name' => json_encode(['en' => 'Rejectable Brand ' . uniqid()]), 'slug' => 'reject-' . uniqid(),
            'image' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Brand::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($admin)->get('/admin/brand/update_status/' . $brand->id . '?status=0');
        $response->assertOk();

        $brand->refresh();
        $this->assertEquals(0, $brand->status);
        $this->assertEquals(Brand::APPROVAL_REJECTED, $brand->approval_status);

        $listResponse = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller.brands.list'));
        $ids = collect($listResponse->json('rows'))->pluck('id')->all();
        $this->assertContains($brand->id, $ids);

        $deleteResponse = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller.brands.destroy', $brand->id));
        $deleteData = $deleteResponse->json();
        $this->assertNotEmpty($deleteData['error'] ?? null);
        $this->assertDatabaseHas('brands', ['id' => $brand->id]);
    }

    public function test_seller_can_delete_their_own_still_pending_brand_request(): void
    {
        [$sellerUser, $seller] = $this->makeSeller('brand-seller-withdraw');

        $brand = Brand::forceCreate([
            'name' => json_encode(['en' => 'Withdrawable Brand ' . uniqid()]), 'slug' => 'withdraw-' . uniqid(),
            'image' => 'x.png', 'status' => 2, 'store_id' => 1,
            'requested_by_seller_id' => $seller->id, 'approval_status' => Brand::APPROVAL_PENDING,
        ]);

        $response = $this->actingAs($sellerUser)->withSession(['store_id' => 1])
            ->get(route('seller.brands.destroy', $brand->id));
        $data = $response->json();

        $this->assertFalse($data['error'] ?? true);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }
}
