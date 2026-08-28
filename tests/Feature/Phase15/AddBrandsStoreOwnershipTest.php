<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\v1\ApiController as SellerApiController;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral (not part of that
 * background agent's original 17 findings): Seller\v1\ApiController::add_brands() took store_id directly
 * from the request with no check the authenticated seller actually manages that store. Since `brands` has
 * no seller_id column of its own - store_id IS the tenant boundary for this table - any logged-in seller
 * could create a Brand row under ANY other seller's store_id. Same class of bug, and same fix pattern
 * (App\Models\SellerStore ownership check), as the 8 findings tests/Feature/Phase2/
 * SellerDeliveryApiOwnershipTest.php already covers for this same controller.
 */
class AddBrandsStoreOwnershipTest extends TestCase
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

    public function test_add_brands_rejects_a_store_id_the_seller_does_not_manage(): void
    {
        [$attackerUser] = $this->makeSellerWithStore(1001);
        Auth::login($attackerUser);

        $response = app(SellerApiController::class)->add_brands(new Request([
            'store_id' => '2002', // a different store, not attacker's own 1001 - string, matching the
            // controller's own 'store_id' => 'required|string' validation rule.
            'brand_name' => 'Hijacked Brand',
            'image' => 'brands/x.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('brands', ['store_id' => 2002]);
    }

    public function test_add_brands_allows_a_seller_to_create_a_brand_in_their_own_store(): void
    {
        [$ownerUser] = $this->makeSellerWithStore(3003);
        Auth::login($ownerUser);

        $response = app(SellerApiController::class)->add_brands(new Request([
            'store_id' => '3003',
            'brand_name' => 'My Own Brand',
            'image' => 'brands/y.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertDatabaseHas('brands', ['store_id' => 3003]);
    }
}
