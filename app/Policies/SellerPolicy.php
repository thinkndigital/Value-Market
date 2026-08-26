<?php

namespace App\Policies;

use App\Models\Seller;
use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): the "Seller/Store" resource named in the Phase 2
 * master prompt - a seller_data record (store settings, commission, payout bank details, KYC documents) is
 * private to the user who owns it (`seller_data.user_id`). This is the same tenant boundary Phase 1
 * established via ProductPolicy for Product ownership (docs/PHASE_1_ARCHITECTURE.md), applied to the
 * seller's own profile/store record itself.
 */
class SellerPolicy
{
    public function view(User $user, Seller $seller): bool
    {
        return (int) $seller->user_id === (int) $user->id;
    }

    public function update(User $user, Seller $seller): bool
    {
        return $this->view($user, $seller);
    }
}
