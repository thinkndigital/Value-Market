<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): centralizes the ownership rule that
 * Admin\AddressController's store()/destroy() methods already enforce inline (Phase 1,
 * docs/SECURITY_AUDIT.md Task 8 - the confirmed address IDOR fix) - an address belongs to the user who
 * created it (`addresses.user_id`), full stop. Available for new call sites; the existing inline checks
 * are left as-is here since they are already correct and covered by
 * tests/Feature/Phase1/AddressOwnershipTest.php - re-verified, not re-implemented, in
 * docs/PHASE_2_IDOR_AUDIT.md (Task 10).
 */
class AddressPolicy
{
    public function view(User $user, Address $address): bool
    {
        return (int) $address->user_id === (int) $user->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $this->view($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->view($user, $address);
    }
}
