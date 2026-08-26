<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 - Storage Engine conversion (docs/PHASE_1_DATABASE_MIGRATION_PLAN.md, Task B).
 *
 * Converts the 11 tables that ship on MyISAM to InnoDB. MyISAM has no transaction support and only
 * table-level locking; four of these eleven tables carry financial/order state (`orders`,
 * `wallet_transactions`) or user-facing transactional data (`return_requests`, `favorites`,
 * `notifications`, `delivery_boy_notifications`) that Phase 1's transaction-boundary work (Task E) and any
 * future accounting/ledger work require to sit on a transactional engine.
 *
 * Confirmed safe: no table in this schema uses a FULLTEXT index (grepped the full structure dump), so
 * there is no MyISAM-only index type blocking the conversion. `ALTER TABLE ... ENGINE=InnoDB` preserves
 * all data, columns, and indexes in place - MySQL/MariaDB rebuild the table under the new engine without
 * requiring a drop/recreate, so this is non-destructive and reversible (see down()).
 *
 * Idempotent: only converts a table if it is not already InnoDB, so this is safe to run against a
 * database that has already had some/all of these tables converted manually.
 */
return new class extends Migration
{
    /** Tables confirmed MyISAM in the audited schema (docs/DATABASE_GAP_ANALYSIS.md §3). */
    private array $tables = [
        'orders',
        'products',
        'wallet_transactions',
        'return_requests',
        'sections',
        'settings',
        'sliders',
        'time_slots',
        'notifications',
        'favorites',
        'delivery_boy_notifications',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $engine = DB::selectOne(
                "SELECT ENGINE as engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->engine ?? null;

            if ($engine !== null && strtoupper($engine) !== 'INNODB') {
                DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE `{$table}` ENGINE=MyISAM");
            }
        }
    }
};
