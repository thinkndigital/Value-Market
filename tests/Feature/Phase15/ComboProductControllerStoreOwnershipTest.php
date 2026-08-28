<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\ComboProductController;
use App\Models\ComboProduct;
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
 * to PosController/BrandController/CategoryController/MediaController. Seller\ComboProductController::store()
 * previously trusted $request->user_id directly (letting a seller attribute a combo product to any other
 * seller's identity) and trusted $request->store_id/session store_id with no ownership check at all;
 * update() had the same unverified store_id write (its seller_id is already protected separately - see
 * SellerPanelIsolationTest::test_combo_update_denies_a_non_owning_seller_and_does_not_steal_ownership).
 */
class ComboProductControllerStoreOwnershipTest extends TestCase
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
        [$attackerUser] = $this->makeSellerWithStore(8301);
        Auth::login($attackerUser);
        session(['store_id' => 8302]);

        $response = app(ComboProductController::class)->store(new Request([
            'title' => 'Hijacked Combo', 'short_description' => 'x', 'image' => 'x.jpg',
        ]), true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('combo_products', ['store_id' => 8302]);
    }

    public function test_store_rejects_a_store_id_the_seller_does_not_manage_even_when_sent_directly_in_the_request(): void
    {
        [$attackerUser] = $this->makeSellerWithStore(8303);
        Auth::login($attackerUser);

        $response = app(ComboProductController::class)->store(new Request([
            'title' => 'Hijacked Combo', 'short_description' => 'x', 'image' => 'x.jpg', 'store_id' => 8304,
        ]), true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('combo_products', ['store_id' => 8304]);
    }

    public function test_store_allows_a_seller_to_create_a_combo_product_in_their_own_store(): void
    {
        [$ownerUser, $owner] = $this->makeSellerWithStore(9301);
        Auth::login($ownerUser);
        session(['store_id' => 9301]);

        $response = app(ComboProductController::class)->store(new Request([
            'title' => 'My Own Combo', 'short_description' => 'x', 'image' => 'x.jpg',
            'product_type_in_combo' => 'digital_product', 'digital_product_id' => [1],
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error'] ?? true, json_encode($data));
        $this->assertDatabaseHas('combo_products', ['store_id' => 9301, 'seller_id' => $owner->id]);
    }

    public function test_store_does_not_let_the_seller_attribute_the_combo_to_another_sellers_identity(): void
    {
        [$ownerUser, $owner] = $this->makeSellerWithStore(9302);
        [, $otherSeller] = $this->makeSellerWithStore(9303);
        Auth::login($ownerUser);
        session(['store_id' => 9302]);

        $response = app(ComboProductController::class)->store(new Request([
            'title' => 'Spoofed Owner Combo', 'short_description' => 'x', 'image' => 'x.jpg',
            'product_type_in_combo' => 'digital_product', 'digital_product_id' => [1],
            'user_id' => $otherSeller->user_id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error'] ?? true, json_encode($data));
        $this->assertDatabaseHas('combo_products', ['store_id' => 9302, 'seller_id' => $owner->id]);
        $this->assertDatabaseMissing('combo_products', ['seller_id' => $otherSeller->id]);
    }

    public function test_update_rejects_a_store_id_the_seller_does_not_manage(): void
    {
        [$ownerUser, $owner] = $this->makeSellerWithStore(9304);
        $combo = ComboProduct::forceCreate([
            'seller_id' => $owner->id, 'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '', 'status' => 1, 'store_id' => 9304,
        ]);
        Auth::login($ownerUser);

        $response = app(ComboProductController::class)->update(new Request([
            'title' => 'Updated', 'short_description' => 'x', 'image' => 'x.jpg', 'store_id' => 9999,
        ]), $combo->id, true);
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(9304, $combo->fresh()->store_id);
    }
}
