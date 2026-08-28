<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\BrandController;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral - the web-panel sibling
 * of tests/Feature/Phase15/AddBrandsStoreOwnershipTest.php (the mobile-API version of this same feature,
 * already fixed). Seller\BrandController::store() trusts StoreService::getStoreId() (session('store_id')),
 * which SetDefaultStore middleware can silently repoint at any store via an unauthenticated `?store=slug`
 * query parameter on any web request. Since `brands` has no seller_id column of its own - store_id IS the
 * tenant boundary - this let a seller create a Brand row under a store they don't manage.
 */
class BrandControllerStoreOwnershipTest extends TestCase
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

    public function test_store_rejects_a_session_store_id_the_seller_does_not_manage(): void
    {
        [$attackerUser] = $this->makeSellerWithStore(8001);
        Auth::login($attackerUser);
        // Simulates the SetDefaultStore hijack: session points at a store this seller doesn't manage.
        session(['store_id' => 8002]);

        $response = app(BrandController::class)->store(new Request([
            'brand_name' => 'Hijacked Brand',
            'image' => 'brands/x.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('brands', ['store_id' => 8002]);
    }

    public function test_store_allows_a_seller_to_create_a_brand_in_their_own_store(): void
    {
        [$ownerUser] = $this->makeSellerWithStore(9001);
        Auth::login($ownerUser);
        session(['store_id' => 9001]);

        $response = app(BrandController::class)->store(new Request([
            'brand_name' => 'My Own Brand',
            'image' => 'brands/y.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error'] ?? false);
        $this->assertDatabaseHas('brands', ['store_id' => 9001]);
    }
}
