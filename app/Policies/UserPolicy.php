<?php

namespace App\Policies;

use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): the "Customer / User / Employee" resource named in
 * the Phase 2 master prompt - a user's own account record (profile, mobile, email, wallet balance, etc.)
 * is private to that user. Super admins already bypass every check via Gate::before(); admin/editor staff
 * are additionally allowed here since managing other users' accounts is their actual job in the admin
 * panel (gated separately, at the route level, by the `role:`/`permissions:` middleware) - this Policy is
 * a self-service safety net for customer/seller/delivery-boy-facing endpoints that take a user id from the
 * request, not the sole gate for the admin panel's user management screens.
 */
class UserPolicy
{
    public function view(User $user, User $target): bool
    {
        if ((int) $target->id === (int) $user->id) {
            return true;
        }

        return $user->isPlatformStaff();
    }

    public function update(User $user, User $target): bool
    {
        return $this->view($user, $target);
    }
}
