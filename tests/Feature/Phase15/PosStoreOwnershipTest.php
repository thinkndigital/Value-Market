<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\PosController;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Found while investigating docs/SECURITY_AUDIT.md §6.2's Model::unguard() deferral: neither
 * PosController::place_order() nor combo_place_order() checked Auth::user() at all before this fix - a POS
 * sale (real stock deduction, wallet/earnings effects) was attributed entirely to whatever store_id the
 * session happened to hold, which SetDefaultStore middleware can silently repoint at any store via an
 * unauthenticated `?store=slug` query parameter. Fixed with TenantContext::verifiedSellerStoreId().
 */
class PosStoreOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_order_rejects_an_unauthenticated_caller(): void
    {
        // No Auth::login() at all - matches a session whose store_id was set by SetDefaultStore without
        // ever authenticating as a seller.
        session(['store_id' => 100]);

        $response = app(PosController::class)->place_order(new Request(['data' => json_encode([])]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }

    public function test_place_order_rejects_a_seller_who_does_not_manage_the_session_store(): void
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => 200,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($user);
        // A different store than the one this seller actually manages - simulates the SetDefaultStore hijack.
        session(['store_id' => 999]);

        $response = app(PosController::class)->place_order(new Request(['data' => json_encode([])]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }

    public function test_combo_place_order_rejects_a_seller_who_does_not_manage_the_session_store(): void
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $user->id, 'disk' => 'public', 'status' => 1]);
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => 300,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($user);
        session(['store_id' => 999]);

        $response = app(PosController::class)->combo_place_order(new Request(['data' => json_encode([])]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
    }
}
