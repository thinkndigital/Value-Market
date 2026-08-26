<?php

namespace App\Services;

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
     * The seller_data id owned by the given user, or null if they don't own one (not a seller, or a seller
     * account whose seller_data row is missing/removed).
     */
    public function sellerIdFor(User $user): ?int
    {
        if (!array_key_exists($user->id, $this->sellerIdCache)) {
            $this->sellerIdCache[$user->id] = Seller::where('user_id', $user->id)->value('id');
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
}
