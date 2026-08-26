<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Services\TenantContext;

/**
 * Phase 1 architecture convention (docs/PHASE_1_ARCHITECTURE.md): tenant/ownership checks in this codebase
 * are currently ad-hoc, re-implemented per controller method (e.g. the manual
 * `$product_details[0]->seller_id !== $seller_id` check this policy replaces in
 * Seller\ProductController::update()). A Policy is the first real example of centralizing that check into
 * one reusable, testable place instead of copy-pasted inline comparisons - see
 * tests/Feature/ProductPolicyTest.php for the tenant-isolation regression test this makes possible.
 *
 * This does not change WHO can do what - it is the same ownership rule the app already enforces
 * (a seller may only manage their own products), just expressed once instead of per call site.
 */
class ProductPolicy
{
    /**
     * A seller may view/update/delete a product only if it belongs to their own seller_data record.
     * Super admins bypass this entirely via the Gate::before() hook in AuthServiceProvider.
     */
    public function manage(User $user, Product $product): bool
    {
        // Phase 2 (docs/PHASE_2_MULTITENANCY.md, Task 6): this lookup now goes through TenantContext,
        // which centralizes and memoizes it, instead of a locally-repeated query - same rule, same result.
        return app(TenantContext::class)->userOwnsSeller($user, (int) $product->seller_id);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->manage($user, $product);
    }
}
