<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\CategoryController;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral - same fix already made
 * to PosController::place_order()/combo_place_order() and BrandController::store(). Seller\
 * CategoryController::store() trusts StoreService::getStoreId() (session('store_id')), which
 * SetDefaultStore middleware can silently repoint at any store via an unauthenticated `?store=slug` query
 * parameter on any web request - letting a seller create a Category row under a store they don't manage.
 */
class CategoryControllerStoreOwnershipTest extends TestCase
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
        [$attackerUser] = $this->makeSellerWithStore(8101);
        Auth::login($attackerUser);
        // Simulates the SetDefaultStore hijack: session points at a store this seller doesn't manage.
        session(['store_id' => 8102]);

        $response = app(CategoryController::class)->store(new Request([
            'name' => 'Hijacked Category',
            'category_image' => 'categories/x.png',
            'banner' => 'categories/banner-x.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('categories', ['store_id' => 8102]);
    }

    public function test_store_allows_a_seller_to_create_a_category_in_their_own_store(): void
    {
        [$ownerUser] = $this->makeSellerWithStore(9101);
        Auth::login($ownerUser);
        session(['store_id' => 9101]);

        $response = app(CategoryController::class)->store(new Request([
            'name' => 'My Own Category',
            'category_image' => 'categories/y.png',
            'banner' => 'categories/banner-y.png',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error'] ?? false, json_encode($data));
        $this->assertDatabaseHas('categories', ['store_id' => 9101]);
    }
}
