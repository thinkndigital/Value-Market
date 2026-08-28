<?php

namespace Tests\Feature\Phase15;

use App\Http\Controllers\Seller\MediaController;
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
 * to PosController/BrandController/CategoryController. Seller\MediaController::upload() previously trusted
 * $request->input('store_id') and $request->input('seller_id') directly, with no ownership check at all -
 * worse than the SetDefaultStore-only cases, since a seller didn't even need to hijack the session, they
 * could just put an arbitrary store_id/seller_id straight in the upload request body. seller_id is now
 * always derived from the authenticated user; store_id is verified via
 * TenantContext::verifiedSellerStoreId() before any file is processed.
 */
class MediaControllerStoreOwnershipTest extends TestCase
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

    public function test_upload_rejects_a_store_id_the_seller_does_not_manage_even_when_sent_directly_in_the_request(): void
    {
        [$attackerUser] = $this->makeSellerWithStore(8201);
        Auth::login($attackerUser);

        // No session hijack needed here - the pre-fix code trusted this straight out of the request body.
        $response = app(MediaController::class)->upload(new Request(['store_id' => 8202]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertDatabaseMissing('media', ['store_id' => 8202]);
    }

    public function test_upload_lets_a_seller_past_the_ownership_check_for_their_own_store(): void
    {
        [$ownerUser] = $this->makeSellerWithStore(9201);
        Auth::login($ownerUser);

        // No 'documents' file attached, so this can't reach a real successful upload - but it proves the
        // request got past the ownership gate: the failure here is "Files not found", not "Data Not Found".
        $response = app(MediaController::class)->upload(new Request(['store_id' => 9201]));
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['error']);
        $this->assertSame('Files not found !', $data['message']);
    }
}
