<?php

namespace Tests\Feature\Phase2;

use App\Models\Role;
use App\Models\Seller;
use App\Models\SellerStore;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2 (docs/PHASE_2_MULTITENANCY.md, Tasks 6-7): TenantContext is the new centralized resolver for
 * "what seller_data tenant does this user belong to" - these tests prove it resolves correctly, memoizes
 * (does not re-query on repeated calls for the same user), and that its ownership predicate actually
 * excludes a non-owner, which is the whole point of introducing it.
 */
class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    private function makeSellerUser(): array
    {
        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
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

        return [$user, $seller];
    }

    public function test_seller_id_for_resolves_the_owning_sellers_id(): void
    {
        [$user, $seller] = $this->makeSellerUser();

        $this->assertSame($seller->id, app(TenantContext::class)->sellerIdFor($user));
    }

    public function test_seller_id_for_returns_null_for_a_user_with_no_seller_record(): void
    {
        $customer = User::forceCreate([
            'username' => 'customer_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
        ]);

        $this->assertNull(app(TenantContext::class)->sellerIdFor($customer));
    }

    public function test_current_seller_id_resolves_from_the_authenticated_user(): void
    {
        [$user, $seller] = $this->makeSellerUser();
        Auth::login($user);

        $this->assertSame($seller->id, app(TenantContext::class)->currentSellerId());
    }

    public function test_user_owns_seller_is_true_for_the_owner_and_false_for_a_stranger(): void
    {
        [$owner, $seller] = $this->makeSellerUser();
        [$stranger] = $this->makeSellerUser();

        $tenantContext = app(TenantContext::class);

        $this->assertTrue($tenantContext->userOwnsSeller($owner, $seller->id));
        $this->assertFalse(
            $tenantContext->userOwnsSeller($stranger, $seller->id),
            'A seller must never be considered the owner of another seller\'s seller_data record.'
        );
    }

    public function test_seller_id_for_is_memoized_and_does_not_re_query(): void
    {
        [$user] = $this->makeSellerUser();
        $tenantContext = app(TenantContext::class);

        $tenantContext->sellerIdFor($user);

        DB::listen(function () {
            $this->fail('sellerIdFor() must not issue a query on a cached lookup for the same user.');
        });

        $tenantContext->sellerIdFor($user);
        $this->assertTrue(true);
    }

    // --- verifiedSellerStoreId() (docs/SECURITY_AUDIT.md §6.2, SetDefaultStore investigation) ---

    private function makeSellerWithStore(int $storeId): User
    {
        [$user, $seller] = $this->makeSellerUser();
        SellerStore::forceCreate([
            'seller_id' => $seller->id, 'user_id' => $user->id, 'store_id' => $storeId,
            'slug' => 'store-' . uniqid(), 'store_name' => 'Store', 'store_description' => 'Store',
            'logo' => '', 'store_thumbnail' => '', 'disk' => 'public', 'store_url' => '',
            'permissions' => json_encode(['require_products_approval' => 0]),
        ]);

        return $user;
    }

    public function test_verified_seller_store_id_returns_the_store_when_the_seller_manages_it(): void
    {
        $user = $this->makeSellerWithStore(4001);
        Auth::login($user);

        $this->assertSame(4001, app(TenantContext::class)->verifiedSellerStoreId(4001));
    }

    public function test_verified_seller_store_id_returns_null_for_a_store_the_seller_does_not_manage(): void
    {
        $user = $this->makeSellerWithStore(4002);
        Auth::login($user);

        $this->assertNull(app(TenantContext::class)->verifiedSellerStoreId(4003));
    }

    public function test_verified_seller_store_id_returns_null_for_a_non_seller(): void
    {
        $admin = User::forceCreate([
            'username' => 'admin_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SUPER_ADMIN,
        ]);
        Auth::login($admin);

        $this->assertNull(app(TenantContext::class)->verifiedSellerStoreId(4004));
    }

    public function test_verified_seller_store_id_returns_null_for_an_empty_candidate(): void
    {
        $user = $this->makeSellerWithStore(4005);
        Auth::login($user);

        $this->assertNull(app(TenantContext::class)->verifiedSellerStoreId(''));
        $this->assertNull(app(TenantContext::class)->verifiedSellerStoreId(null));
    }
}
