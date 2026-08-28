<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\ProductController;
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
 * Found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral (not part of the
 * original background-agent audit): Seller\ProductController::store() took `seller_id` directly from the
 * request with no verification at all (its own 'seller_id' => 'required' validation rule is commented
 * out) - any authenticated seller could create a product attributed to ANY other seller's identity, and
 * forge the seller_id/store_id pair the auto-approval permissions lookup uses. This method also backs
 * admin/products (routes/admin_routes.php), where an admin/editor legitimately chooses the seller - the fix
 * only applies the check when the caller is actually a seller.
 */
class ProductStoreSellerIdentityTest extends TestCase
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

    public function test_a_seller_cannot_create_a_product_under_a_store_they_do_not_manage(): void
    {
        [, $victimSeller] = $this->makeSellerWithStore(5001);
        [$attackerUser] = $this->makeSellerWithStore(5002);
        Auth::login($attackerUser);

        // The controller's own `$store_id = ... request('store_id') ...` reads the global request() helper,
        // not the $request parameter - bind this request into the container so it resolves correctly, same
        // gotcha documented in tests/Feature/Phase1/AddressOwnershipTest.php.
        $request = new Request([
            'store_id' => 5001, // not the attacker's own store
            'seller_id' => $victimSeller->id, // attacker directly claims the victim's identity
            'pro_input_name' => 'Impersonated Product',
            'short_description' => 'x',
            'category_id' => 1,
            'pro_input_image' => 'x.png',
            'product_type' => 'simple_product',
            'deliverable_type' => 1,
            'simple_price' => 10,
        ]);
        $this->app->instance('request', $request);

        $response = app(ProductController::class)->store($request, true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, Product::where('seller_id', $victimSeller->id)->count());
    }

    public function test_a_sellers_own_seller_id_is_enforced_even_if_they_submit_a_different_one(): void
    {
        [$ownerUser, $ownerSeller] = $this->makeSellerWithStore(6001);
        [, $otherSeller] = $this->makeSellerWithStore(6002);
        Auth::login($ownerUser);

        $request = new Request([
            'store_id' => 6001, // the seller's own store
            'seller_id' => $otherSeller->id, // tries to claim a different seller's identity anyway
            'pro_input_name' => 'My Product',
            'short_description' => 'x',
            'category_id' => 1,
            'pro_input_image' => 'x.png',
            'product_type' => 'simple_product',
            'deliverable_type' => 1,
            'simple_price' => 10,
        ]);
        $this->app->instance('request', $request);

        app(ProductController::class)->store($request, true);

        // Regardless of whether the rest of the (long, form-heavy) method goes on to successfully persist
        // the product, the request's own seller_id must have been overwritten to the real owner's id before
        // any of that downstream logic reads it - proving the override happens, not just that the attacker
        // wasn't outright blocked.
        $this->assertSame((string) $ownerSeller->id, (string) $request->input('seller_id'));
        $this->assertSame(0, Product::where('seller_id', $otherSeller->id)->count());
    }

    /**
     * This method also backs admin/products (routes/admin_routes.php) - an admin/editor legitimately
     * chooses which seller a product belongs to, so the seller-only check above must not fire for them.
     */
    public function test_an_admin_choosing_a_sellers_id_is_not_overridden_or_rejected(): void
    {
        [, $targetSeller] = $this->makeSellerWithStore(7001);
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);

        $request = new Request([
            'store_id' => 7001,
            'seller_id' => $targetSeller->id, // the admin choosing which seller this product belongs to
            'pro_input_name' => 'Admin-Assigned Product',
            'short_description' => 'x',
            'category_id' => 1,
            'pro_input_image' => 'x.png',
            'product_type' => 'simple_product',
            'deliverable_type' => 1,
            'simple_price' => 10,
        ]);
        $this->app->instance('request', $request);

        $response = app(ProductController::class)->store($request, true);
        $data = json_decode($response->getContent(), true);

        // Not the seller-ownership "Data Not Found" rejection - whatever this response is (success, or a
        // validation failure for an unrelated missing field), it must not be that specific rejection, and
        // seller_id must be untouched.
        $this->assertNotSame(labels('seller.data_not_found', 'Data Not Found'), $data['message'] ?? null);
        $this->assertSame((string) $targetSeller->id, (string) $request->input('seller_id'));
    }
}
