<?php

namespace Tests\Feature\Phase1;

use App\Models\Category;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_ARCHITECTURE.md, Task G): tenant isolation regression test. This is the concrete
 * proof behind the Task G finding that `seller_data` (Seller), not `stores`, is the real tenant boundary
 * in this codebase today - a seller must never be able to manage another seller's product.
 *
 * Writing this test surfaced a real, separate finding kept out of scope for this Phase 1 pass (documented
 * in docs/PHASE_1_DATA_INTEGRITY_REPORT.md "Known risks"): AuthServiceProvider's Gate::before() hook does
 * `$user->role->name` with no null check, and both RoleMiddleware and CheckPermissions do the same - any
 * user with role_id = NULL (a legitimate, nullable column - most plain customers likely have no role_id at
 * all) hitting ANY Gate/Policy/role-middleware check would fatal-error. This test works around that by
 * always assigning a real role to every test user, matching how admin/seller users are provisioned in
 * practice; it does not fix the underlying null-safety gap, which is an authorization code change outside
 * Phase 1's database-foundation scope.
 */
class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** roles has no created_at/updated_at columns, but the Role model doesn't disable timestamps - another
     *  small pre-existing mismatch (docs/PHASE_1_DATA_INTEGRITY_REPORT.md) that only surfaces if something
     *  tries to create a Role through Eloquent, which nothing in the app currently does. Insert raw here to
     *  sidestep it rather than "fix" a model nothing in this phase is meant to touch. */
    private function makeRole(string $name): int
    {
        return DB::table('roles')->insertGetId(['name' => $name, 'description' => $name]);
    }

    private function makeSellerWithProduct(): array
    {
        // A fresh role per call, not memoized: RefreshDatabase resets the schema/data between tests, so a
        // statically-cached role id from a previous test would point at a row that no longer exists.
        $sellerRoleId = $this->makeRole('seller_' . uniqid());

        $user = User::forceCreate([
            'username' => 'seller_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => $sellerRoleId,
        ]);

        // seller_data's real columns are just user_id/disk/status/etc. - store_name, store_url etc. are on
        // the seller_store pivot table, NOT here, despite Seller::$fillable listing them (a pre-existing
        // model/schema mismatch discovered while writing this test - see
        // docs/PHASE_1_DATA_INTEGRITY_REPORT.md "Known risks").
        $seller = Seller::forceCreate([
            'user_id' => $user->id,
            'disk' => 'public',
        ]);

        $category = Category::forceCreate([
            'name' => json_encode(['en' => 'Category']),
            'slug' => 'cat-' . uniqid(),
            'image' => '',
            'banner' => '',
        ]);

        $product = Product::forceCreate([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
            'name' => json_encode(['en' => 'Product']),
            'slug' => 'product-' . uniqid(),
            'image' => '',
            'deliverable_cities' => '',
        ]);

        return [$user, $seller, $product];
    }

    public function test_a_seller_can_manage_their_own_product(): void
    {
        [$owner, , $product] = $this->makeSellerWithProduct();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $product));
    }

    public function test_a_seller_cannot_manage_another_sellers_product(): void
    {
        [, , $product] = $this->makeSellerWithProduct();
        [$otherOwner] = $this->makeSellerWithProduct();

        $this->assertTrue(
            Gate::forUser($otherOwner)->denies('update', $product),
            'A seller must never be authorized to manage a product belonging to a different seller.'
        );
    }

    public function test_super_admin_bypasses_the_ownership_check(): void
    {
        [, , $product] = $this->makeSellerWithProduct();

        $superAdminRoleId = $this->makeRole('super_admin');
        $superAdmin = User::forceCreate([
            'username' => 'super_admin_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => $superAdminRoleId,
        ]);

        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $product));
    }
}
