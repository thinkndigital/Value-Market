<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds one default row into `storage_types` (id 1, name "public", is_default 1).
 *
 * Same gap as 2025_02_01_000000_seed_roles_reference_data.php: the baseline migrations recreate every
 * table's structure but not its seed data, and `storage_types` is not optional demo content either. Every
 * media-upload code path in the app (StoreController, ProductController, ComboProductController,
 * SellerController, Delivery_boyController, UserController, MediaController - eleven call sites in total)
 * does the same `fetchDetails(StorageType::class, ['is_default' => 1], '*')`, falls back to a hardcoded
 * `id = 1` when that's empty, then calls `StorageType::find($id)->addMedia(...)` with no null check. On a
 * fresh install with an empty `storage_types` table, `find(1)` returns null and every one of those flows -
 * including the very first "Add Store" submission - fails with an uncaught "Call to a member function
 * addMedia() on null" (a 500, not caught by any of those methods' `catch (Exception $e)` blocks, since a
 * null-method-call is a PHP \Error, not an \Exception).
 *
 * Idempotent: only inserts when no default storage type already exists, so safe against a database that
 * already has one configured (real eShop Plus installs, which have it from the SQL installer) - deliberately
 * checking `is_default` rather than `id` doesn't already exist, since what actually matters is that at least
 * one row is marked default.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasDefault = DB::table('storage_types')->where('is_default', 1)->exists();

        if (!$hasDefault) {
            DB::table('storage_types')->insert([
                'name' => 'public',
                'is_default' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally no-op: media rows created since this migration ran may reference this storage
        // type's disk, so removing it on rollback would strand them the same way the roles seed migration's
        // down() leaves that data alone.
    }
};
