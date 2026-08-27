<?php

namespace Tests\Feature\Phase6;

use App\Http\Controllers\Seller\PosController;
use App\Models\ComboProduct;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Seller;
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

        Auth::login($sellerUser);

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
}
