<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\AttributeController;
use App\Http\Controllers\Seller\ComboProductFaqController;
use App\Models\Attribute;
use App\Models\ComboProduct;
use App\Models\ComboProductFaq;
use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Continuing docs/SECURITY_AUDIT.md §6.4's sweep into read-only call sites: ComboProductFaqController::
 * list() and AttributeController::list()/getAttributeValue() had no ownership check on store_id (Attribute/
 * Attribute_values have no seller_id concept at all; ComboProductFaqController's other methods already
 * verify per-record ownership via TenantContext::userOwnsSeller(), but list() only filtered by store_id).
 */
class ComboProductFaqAndAttributeReadOwnershipTest extends TestCase
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

    public function test_combo_product_faq_list_rejects_a_hijacked_session_store_id(): void
    {
        [, $victimSeller] = $this->makeSellerWithStore(8501);
        $victimCombo = ComboProduct::forceCreate([
            'seller_id' => $victimSeller->id, 'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '', 'status' => 1, 'store_id' => 8501,
        ]);
        ComboProductFaq::forceCreate([
            'product_id' => $victimCombo->id, 'question' => 'Victim Q', 'answer' => 'Victim A',
            'user_id' => $victimSeller->user_id, 'seller_id' => $victimSeller->id,
        ]);

        [$attackerUser] = $this->makeSellerWithStore(8502);
        Auth::login($attackerUser);
        session(['store_id' => 8501]);

        $response = app(ComboProductFaqController::class)->list(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['rows']);
    }

    public function test_combo_product_faq_list_allows_the_owning_seller(): void
    {
        [$ownerUser, $owner] = $this->makeSellerWithStore(8503);
        $combo = ComboProduct::forceCreate([
            'seller_id' => $owner->id, 'title' => json_encode(['en' => 'Combo']),
            'deliverable_cities' => '', 'status' => 1, 'store_id' => 8503,
        ]);
        ComboProductFaq::forceCreate([
            'product_id' => $combo->id, 'question' => 'Q', 'answer' => 'A',
            'user_id' => $owner->user_id, 'seller_id' => $owner->id,
        ]);
        Auth::login($ownerUser);
        session(['store_id' => 8503]);

        $response = app(ComboProductFaqController::class)->list(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['total']);
    }

    public function test_attribute_list_rejects_a_hijacked_session_store_id(): void
    {
        Attribute::forceCreate(['name' => 'Victim Attr', 'store_id' => 8504, 'status' => 1]);
        [$attackerUser] = $this->makeSellerWithStore(8505);
        Auth::login($attackerUser);
        session(['store_id' => 8504]);

        $response = app(AttributeController::class)->list(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['total']);
        $this->assertSame([], $data['rows']);
    }

    public function test_attribute_list_allows_the_owning_seller(): void
    {
        Attribute::forceCreate(['name' => 'My Attr', 'store_id' => 8506, 'status' => 1]);
        [$ownerUser] = $this->makeSellerWithStore(8506);
        Auth::login($ownerUser);
        session(['store_id' => 8506]);

        $response = app(AttributeController::class)->list(new Request());
        $data = json_decode($response->getContent(), true);

        $this->assertSame(1, $data['total']);
    }

    public function test_get_attribute_value_rejects_a_store_id_sent_directly_in_the_request(): void
    {
        [$attackerUser] = $this->makeSellerWithStore(8507);
        Auth::login($attackerUser);

        $response = app(AttributeController::class)->getAttributeValue(new Request(['store_id' => 8508]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame(0, $data['total']);
    }
}
