<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 4): named constants for the legacy `roles` table's
     * real row IDs, verified against the actual seeded data (id => name):
     * 1 => super_admin, 2 => members, 3 => delivery_boy, 4 => seller, 5 => admin, 6 => editor.
     * These replace the ~43 hardcoded numeric role_id comparisons found across the codebase - same values,
     * named instead of magic numbers, so a call site reads `Role::DELIVERY_BOY` instead of `3`. This does
     * NOT migrate the underlying mechanism to Spatie roles (which are unused - see PHASE_2_RBAC_AUDIT.md
     * §2b) - it only removes the magic numbers from the mechanism that actually gates behavior today.
     */
    public const SUPER_ADMIN = 1;
    public const CUSTOMER = 2; // roles.name is 'members', but every caller in the app treats this as "customer"
    public const DELIVERY_BOY = 3;
    public const SELLER = 4;
    public const ADMIN = 5;
    public const EDITOR = 6;
    public const WHOLESALER = 7; // SaaS re-architecture brief - see 2025_02_21_000000_create_wholesaler_module.php

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
