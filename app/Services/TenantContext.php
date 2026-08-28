<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 2 (docs/PHASE_2_MULTITENANCY.md, Tasks 6-7): centralizes "what seller_data tenant does this user
 * belong to" - the single query (`Seller::where('user_id', $user->id)->value('id')`) that Phase 1's
 * architecture audit (docs/PHASE_1_ARCHITECTURE.md, Task G) found copy-pasted independently well over 90
 * times across the Seller-panel controllers, with no central place enforcing it.
 *
 * This does not change the tenant model - seller_data is still the tenant boundary Phase 1 established, and
 * this class resolves against that exact same table/relationship. It is a resolver, not a new abstraction:
 * given the current (or a specified) authenticated user, answer "what seller_id do they own" and "do they
 * own this particular seller_id", once, memoized per-request instead of re-querying on every call.
 *
 * Existing controller code that already does the inline query is left as-is (rewriting ~90 already-working
 * call sites in one pass is exactly the kind of large, high-risk refactor this phase's master prompt rules
 * out) - new/fixed code from this phase's IDOR sweep (docs/PHASE_2_IDOR_AUDIT.md, Tasks 8-9) uses this
 * class instead of adding another copy of the inline pattern.
 */
class TenantContext
{
    /** @var array<int, int|null> */
    private array $sellerIdCache = [];

    /**
     * The seller_data id this user acts as - either the seller_data they own directly, or (Phase 4,
     * docs/PHASE_4_VENDOR_SYSTEM.md) the seller_data of the seller who employs them, for an active
     * `employees` row. Null if neither applies.
     *
     * Phase 4 scope boundary: this resolver is the one place employee tenant-scoping is centralized, and
     * every caller that already goes through TenantContext (all Phase 2/3 fixes, and Phase 4's own
     * Branch/EmployeeController) picks up employee support automatically. It does NOT retroactively fix the
     * ~90 pre-existing call sites across the Seller panel that inline the equivalent
     * `Seller::where('user_id', ...)->value('id')` query directly (documented in Phase 2's own
     * TenantContext introduction as a deliberately deferred large-surface rewrite) - an employee logging
     * into the Seller panel today will not yet see products/orders/POS through those unmigrated
     * controllers. See docs/PHASE_4_VENDOR_SYSTEM.md for the full explanation and the follow-up this leaves.
     */
    public function sellerIdFor(User $user): ?int
    {
        if (!array_key_exists($user->id, $this->sellerIdCache)) {
            $ownSellerId = Seller::where('user_id', $user->id)->value('id');

            if ($ownSellerId === null) {
                $ownSellerId = Employee::where('user_id', $user->id)
                    ->where('status', Employee::STATUS_ACTIVE)
                    ->value('seller_id');
            }

            $this->sellerIdCache[$user->id] = $ownSellerId;
        }

        return $this->sellerIdCache[$user->id];
    }

    /**
     * The seller_data id owned by the currently authenticated user, or null if there is no authenticated
     * user or they don't own a seller_data record.
     */
    public function currentSellerId(): ?int
    {
        $user = Auth::user();

        return $user ? $this->sellerIdFor($user) : null;
    }

    /**
     * Whether the given user owns the given seller_data id - the actual tenant-isolation predicate: use
     * this (or currentSellerId() plus a direct comparison) instead of trusting a request-supplied
     * seller_id, store_id, or seller_data foreign key at face value.
     */
    public function userOwnsSeller(User $user, int $sellerId): bool
    {
        $ownedId = $this->sellerIdFor($user);

        return $ownedId !== null && $ownedId === $sellerId;
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 9): sellerIdFor()/currentSellerId()
     * deliberately resolve the same seller_id for the seller owner AND any of their active employees, so
     * every controller that only checks "does this resolve to my seller_id" (correct for products, orders,
     * POS, ...) was also, unintentionally, giving every employee full owner authority over the roster
     * itself - creating more employees, deactivating others, reassigning branches. This is the one predicate
     * that distinguishes "the actual owner account" from "an employee acting for that owner's tenant" - use
     * it to gate employee-management actions specifically, not general tenant-scoped data access.
     */
    public function isSellerOwner(User $user): bool
    {
        return Seller::where('user_id', $user->id)->exists();
    }

    /**
     * Security fix (docs/SECURITY_AUDIT.md §6.2, the ongoing SetDefaultStore/StoreService::getStoreId()
     * investigation): StoreService::getStoreId() reads session('store_id'), which SetDefaultStore
     * middleware can silently repoint at ANY store via an unauthenticated `?store=slug` query parameter on
     * any web request - a legitimate feature for anonymous customers browsing a specific seller's public
     * storefront, but never intended to also decide which store an authenticated SELLER is acting on
     * behind the scenes. This is the one place that verifies a candidate store_id (from getStoreId() or a
     * request parameter - callers keep their own existing resolution logic, this only verifies the result)
     * actually belongs to the acting seller, via the same App\Models\SellerStore ownership check already
     * applied individually to add_brands()/ProductController::store()/BrandController::store() before this
     * method existed. Returns null (never the unverified value) if the caller isn't a seller, has no
     * candidate store_id, or doesn't manage the one given - callers must treat null as "not authorized",
     * not "no store selected."
     *
     * Deliberately does not attempt to resolve a fallback store_id itself (e.g. "pick their first store") -
     * that would be a behavior change for READ endpoints (which endpoint should show which store's default
     * data is a product decision, not a security one) and this method's only job is verification.
     */
    public function verifiedSellerStoreId($candidateStoreId): ?int
    {
        $user = Auth::user();
        if ($user === null || !$user->isSeller() || empty($candidateStoreId)) {
            return null;
        }

        $owns = \App\Models\SellerStore::where('user_id', $user->id)->where('store_id', $candidateStoreId)->exists();

        return $owns ? (int) $candidateStoreId : null;
    }
}
