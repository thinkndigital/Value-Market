<?php

namespace Tests\Feature\Phase6;

use App\Http\Controllers\Seller\PosController;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 6 (docs/PHASE_6_POS.md): PosController::combo_place_order() called
 * ComboProductService::validateComboStock() before creating the order (checking availability) but never
 * actually called updateComboStock() afterwards - stock was validated, then silently never decremented.
 */
class PosComboStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_combo_pos_sale_decrements_combo_stock(): void
    {
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Setting::forceCreate([
            'variable' => 'system_settings',
            'value' => json_encode(['single_seller_order_system' => '0']),
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'combo_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);

        $combo = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo Deal']),
            'slug' => 'combo-deal-' . uniqid(),
            'seller_id' => $seller->id,
            'price' => 50,
            'deliverable_cities' => '',
            'stock' => '10',
            'availability' => 1,
            'status' => 1,
        ]);

        $customer = User::forceCreate([
            'username' => 'combo_customer_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'mobile' => (string) random_int(6000000000, 6999999999),
        ]);

        // Security fix (docs/SECURITY_AUDIT.md §6.2): combo_place_order() now verifies the acting seller
        // manages the session's store_id (TenantContext::verifiedSellerStoreId()).
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 100,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($sellerUser);
        session(['store_id' => 100]);

        $request = new Request([
            'data' => json_encode([
                ['id' => $combo->id, 'title' => 'Combo Deal', 'quantity' => 3, 'price' => 50],
            ]),
            'payment_method' => 'cash',
            'user_id' => $customer->id,
            'final_total' => 150,
            'sub_total' => 150,
        ]);

        $response = app(PosController::class)->combo_place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'combo_place_order should succeed: ' . json_encode($payload));
        $this->assertSame(7, (int) $combo->fresh()->stock);
    }

    /**
     * Live QA finding (docs/POS_LIVE_QA_REVERIFICATION.md): combo_place_order() indexed
     * fetchDetails(User::class, ...)[0]->mobile with no guard at all (worse than the equivalent bug in
     * place_order(), which at least had a broken `!empty()` check). Unlike place_order() (regular
     * products), combo_place_order() requires a user_id to be present at all (asserted separately below) -
     * but a stale/invalid user_id (referencing no real user - e.g. a deleted customer) still crashed this
     * exact line with "Undefined array key 0" instead of just recording an empty mobile number.
     */
    public function test_a_combo_sale_with_a_stale_customer_id_succeeds_instead_of_crashing(): void
    {
        Currency::forceCreate([
            'name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => 1, 'is_default' => 1, 'status' => 1,
        ]);
        Setting::forceCreate([
            'variable' => 'system_settings',
            'value' => json_encode(['single_seller_order_system' => '0']),
        ]);

        $sellerUser = User::forceCreate([
            'username' => 'combo_seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);

        $combo = ComboProduct::forceCreate([
            'title' => json_encode(['en' => 'Combo Deal']),
            'slug' => 'combo-deal-' . uniqid(),
            'seller_id' => $seller->id,
            'price' => 50,
            'deliverable_cities' => '',
            'stock' => '10',
            'availability' => 1,
            'status' => 1,
        ]);

        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $sellerUser->id, 'store_id' => 100,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);
        Auth::login($sellerUser);
        session(['store_id' => 100]);

        $request = new Request([
            'data' => json_encode([
                ['id' => $combo->id, 'title' => 'Combo Deal', 'quantity' => 1, 'price' => 50],
            ]),
            'payment_method' => 'cash',
            'user_id' => 999999,
            'final_total' => 50,
            'sub_total' => 50,
        ]);

        $response = app(PosController::class)->combo_place_order($request);
        $payload = json_decode($response->getContent(), true);

        $this->assertFalse($payload['error'] ?? true, 'combo_place_order should not crash on a stale user_id: ' . json_encode($payload));
        $this->assertSame(9, (int) $combo->fresh()->stock);
    }
}
