<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\Seller\ComboProductFaqController;
use App\Http\Controllers\Seller\ProductFaqController;
use App\Models\ComboProductFaq;
use App\Models\ProductFaq;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Phase 2 (docs/PHASE_2_MULTITENANCY.md, Tasks 6-7): regression test for a confirmed IDOR found during the
 * tenant isolation audit - Seller\ProductFaqController and its combo-product twin looked up a FAQ purely by
 * id, with no check that it belonged to the requesting seller. Any authenticated seller (web panel or the
 * app API - Seller\v1\ApiController::edit_product_faq()/delete_product_faq()) could view, edit, toggle the
 * status of, or delete another seller's product FAQ just by passing its id. This proves the fix actually
 * blocks that, not just that the code changed.
 */
class ProductFaqOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerWithFaq(): array
    {
        $user = User::forceCreate([
            'username' => 'faq_seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);

        $faq = ProductFaq::forceCreate([
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'question' => 'Does it work?',
            'answer' => null,
            'answered_by' => 0,
        ]);

        return [$user, $seller, $faq];
    }

    private function makeSellerWithComboFaq(): array
    {
        $user = User::forceCreate([
            'username' => 'combo_faq_seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::SELLER,
        ]);

        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);

        $faq = ComboProductFaq::forceCreate([
            'user_id' => $user->id,
            'seller_id' => $seller->id,
            'question' => 'Does it work?',
            'answer' => null,
            'answered_by' => 0,
        ]);

        return [$user, $seller, $faq];
    }

    public function test_a_seller_cannot_delete_another_sellers_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithFaq();
        [$attacker] = $this->makeSellerWithFaq();
        Auth::login($attacker);

        app(ProductFaqController::class)->destroy($victimFaq->id);

        $this->assertNotNull(ProductFaq::find($victimFaq->id));
    }

    public function test_a_seller_cannot_edit_another_sellers_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithFaq();
        [$attacker] = $this->makeSellerWithFaq();
        Auth::login($attacker);

        $response = app(ProductFaqController::class)->edit($victimFaq->id);

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertTrue($payload['error'] ?? false, 'Another seller\'s FAQ must not be returned.');
    }

    public function test_a_seller_cannot_toggle_status_of_another_sellers_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithFaq();
        [$attacker] = $this->makeSellerWithFaq();
        Auth::login($attacker);

        $originalStatus = $victimFaq->status;
        app(ProductFaqController::class)->update_status($victimFaq->id);

        $this->assertSame($originalStatus, ProductFaq::find($victimFaq->id)->status);
    }

    public function test_a_seller_cannot_answer_another_sellers_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithFaq();
        [$attacker] = $this->makeSellerWithFaq();
        Auth::login($attacker);

        $request = new Request(['answer' => 'Hijacked answer']);
        app(ProductFaqController::class)->update($request, $victimFaq->id);

        $this->assertNull(ProductFaq::find($victimFaq->id)->answer);
    }

    public function test_a_seller_can_manage_their_own_product_faq(): void
    {
        [$owner, , $faq] = $this->makeSellerWithFaq();
        Auth::login($owner);

        $editResponse = app(ProductFaqController::class)->edit($faq->id);
        $this->assertSame(200, $editResponse->getStatusCode());
        $payload = json_decode($editResponse->getContent(), true);
        $this->assertArrayNotHasKey('error', $payload);

        $request = new Request(['answer' => 'Yes it does.']);
        app(ProductFaqController::class)->update($request, $faq->id);
        $this->assertSame('Yes it does.', ProductFaq::find($faq->id)->answer);

        app(ProductFaqController::class)->destroy($faq->id);
        $this->assertNull(ProductFaq::find($faq->id));
    }

    // --- ComboProductFaqController (the same fix, combo-product twin) ----------------------------------

    public function test_a_seller_cannot_delete_another_sellers_combo_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithComboFaq();
        [$attacker] = $this->makeSellerWithComboFaq();
        Auth::login($attacker);

        app(ComboProductFaqController::class)->destroy($victimFaq->id);

        $this->assertNotNull(ComboProductFaq::find($victimFaq->id));
    }

    public function test_a_seller_cannot_answer_another_sellers_combo_product_faq(): void
    {
        [, , $victimFaq] = $this->makeSellerWithComboFaq();
        [$attacker] = $this->makeSellerWithComboFaq();
        Auth::login($attacker);

        $request = new Request(['answer' => 'Hijacked answer']);
        app(ComboProductFaqController::class)->update($request, $victimFaq->id);

        $this->assertNull(ComboProductFaq::find($victimFaq->id)->answer);
    }
}
