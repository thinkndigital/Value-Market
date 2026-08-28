<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\ProductController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while continuing the docs/SECURITY_AUDIT.md §6.4 sweep to its remaining READ-only call sites.
 * Unlike most other getStoreId() read call sites (which also filter by the authenticated seller's own
 * seller_id, so a hijacked store_id can only narrow results toward empty), these four methods in
 * Seller\ProductController had NO seller_id filter at all - store_id was the only tenant boundary, taken
 * from a SetDefaultStore-hijackable session (or, for get_brands(), directly from the request).
 * edit() is the most severe: a store can host multiple sellers, so even without any session hijack, one
 * seller could load the full edit-form data (pricing, shipping location, brand, stock) of a co-seller's
 * product in the same store, since only store_id - not ownership - gated it.
 */
class ProductControllerReadOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerWithStore(int $storeId): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public']);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $storeId,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return [$user, $seller];
    }

    private function makeProductIn(int $storeId, int $sellerId): Product
    {
        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']), 'slug' => 'cat-' . uniqid(), 'image' => '', 'banner' => '',
        ]);

        return Product::forceCreate([
            'category_id' => $category->id, 'seller_id' => $sellerId, 'store_id' => $storeId,
            'name' => json_encode(['en' => 'Product']), 'slug' => 'product-' . uniqid(),
            'image' => '', 'deliverable_cities' => '', 'status' => 1,
        ]);
    }

    public function test_edit_denies_a_co_seller_in_the_same_store_from_viewing_another_sellers_product(): void
    {
        [, $owner] = $this->makeSellerWithStore(8401);
        $product = $this->makeProductIn(8401, $owner->id);

        [$coSellerUser] = $this->makeSellerWithStore(8401);
        Auth::login($coSellerUser);
        session(['store_id' => 8401]);

        $view = app(ProductController::class)->edit($product->id);

        $this->assertSame('admin.pages.views.no_data_found', $view->name());
    }

    public function test_edit_allows_the_owning_seller_to_view_their_own_product(): void
    {
        [$ownerUser, $owner] = $this->makeSellerWithStore(8402);
        $product = $this->makeProductIn(8402, $owner->id);
        Auth::login($ownerUser);
        session(['store_id' => 8402]);

        $view = app(ProductController::class)->edit($product->id);

        $this->assertSame('seller.pages.forms.update_product', $view->name());
    }

    public function test_get_brands_rejects_a_hijacked_session_store_id(): void
    {
        $storeId = 8403;
        [$attackerUser] = $this->makeSellerWithStore($storeId);
        Brand::forceCreate(['name' => json_encode(['en' => 'Victim Brand']), 'store_id' => 8404, 'status' => 1, 'image' => '']);
        Auth::login($attackerUser);
        session(['store_id' => 8404]);

        $result = app(ProductController::class)->get_brands(new Request());

        $this->assertSame([], $result);
    }

    public function test_get_brands_allows_the_owning_seller_to_list_their_own_store_brands(): void
    {
        $storeId = 8405;
        [$ownerUser] = $this->makeSellerWithStore($storeId);
        Brand::forceCreate(['name' => json_encode(['en' => 'My Brand']), 'store_id' => $storeId, 'status' => 1, 'image' => '']);
        Auth::login($ownerUser);
        session(['store_id' => $storeId]);

        $result = app(ProductController::class)->get_brands(new Request());

        $this->assertCount(1, $result);
    }

    public function test_getbrands_rejects_a_hijacked_session_store_id(): void
    {
        $storeId = 8406;
        [$attackerUser] = $this->makeSellerWithStore($storeId);
        Brand::forceCreate(['name' => json_encode(['en' => 'Victim Brand']), 'store_id' => 8407, 'status' => 1, 'image' => '']);
        Auth::login($attackerUser);
        session(['store_id' => 8407]);

        $result = app(ProductController::class)->getBrands(new Request());

        $this->assertSame([], $result);
    }

    public function test_getdigitalproductdata_rejects_a_hijacked_session_store_id(): void
    {
        $storeId = 8408;
        [$attackerUser] = $this->makeSellerWithStore($storeId);
        Auth::login($attackerUser);
        session(['store_id' => 8409]);

        $response = app(ProductController::class)->getDigitalProductData(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['results']);
    }
}
