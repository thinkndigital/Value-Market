<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 4): seeds the `roles` table with its real production
 * id/name/description rows.
 *
 * This is a Phase 1 gap discovered while working on Phase 2: the baseline migrations
 * (2025_01_01_*_baseline_*.php) reproduce every table's *structure* from the audited schema dump, but
 * deliberately never carried over row *data* (the dump's 18 seed-data INSERT statements) - reasonable for
 * genuinely optional demo content, but `roles` is not that: the whole authorization system (RoleMiddleware,
 * CheckPermissions, Role::SUPER_ADMIN and friends in app/Models/Role.php) depends on these 6 specific
 * id -> name mappings existing. Without this, a fresh install of this codebase from the baseline migrations
 * alone would have an empty `roles` table and no way to designate a super admin at all.
 *
 * Idempotent: only inserts rows whose id doesn't already exist, so safe against a database that already
 * has this data (real eShop Plus installs, which have it from the SQL installer) or partial seed data.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'super_admin', 'description' => 'Administrator'],
            ['id' => 2, 'name' => 'members', 'description' => 'General User'],
            ['id' => 3, 'name' => 'delivery_boy', 'description' => 'Delivery Boys'],
            ['id' => 4, 'name' => 'seller', 'description' => 'Sellers'],
            ['id' => 5, 'name' => 'admin', 'description' => 'Admin'],
            ['id' => 6, 'name' => 'editor', 'description' => 'Editor'],
        ];

        $existingIds = DB::table('roles')->pluck('id')->all();

        foreach ($roles as $role) {
            if (!in_array($role['id'], $existingIds, true)) {
                DB::table('roles')->insert($role);
            }
        }
    }

    public function down(): void
    {
        // Intentionally no-op: these rows are referenced by users.role_id (no FK, but logically required),
        // so removing them on rollback would strand any user created since this migration ran.
    }
};
