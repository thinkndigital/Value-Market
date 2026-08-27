<?php

namespace Tests\Feature;

use App\Models\StorageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the 2025_02_02_000000_seed_default_storage_type migration: without a default
 * storage_types row, every media-upload flow in the app (StoreController::store() among ~10 others) calls
 * StorageType::find($id)->addMedia(...) with no null check, and a fresh install's empty storage_types table
 * turns that into an uncaught "Call to a member function addMedia() on null" on the very first upload -
 * confirmed by reproducing the crash on admin.stores.store before this migration existed.
 */
class DefaultStorageTypeSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_default_storage_type_exists_after_migrating(): void
    {
        $this->assertTrue(StorageType::where('is_default', 1)->exists());
    }

    public function test_it_does_not_duplicate_an_existing_default(): void
    {
        // Re-running the migration's up() (e.g. a database that already has its own default from the SQL
        // installer, or `migrate:fresh` re-applying it) must stay a no-op, not add a second default row.
        StorageType::query()->delete();
        StorageType::forceCreate(['name' => 's3', 'is_default' => 1]);

        $migration = require database_path('migrations/2025_02_02_000000_seed_default_storage_type.php');
        $migration->up();

        $this->assertSame(1, StorageType::where('is_default', 1)->count());
        $this->assertSame('s3', StorageType::where('is_default', 1)->first()->name);
    }
}
