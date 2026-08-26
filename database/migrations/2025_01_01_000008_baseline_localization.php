<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - localization domain.
 *
 * Reproduces the eShop Plus 1.0.6 schema exactly as captured in the audited structure dump
 * (docs/DATABASE_GAP_ANALYSIS.md). This is a snapshot, not a redesign: every CREATE TABLE / index /
 * constraint statement below is taken verbatim from the production schema dump, re-verified by importing
 * it into a scratch MariaDB instance during Phase 1 (see docs/PHASE_1_DATABASE_MIGRATION_PLAN.md).
 *
 * Idempotent by design: each table is only created (and only gets its indexes/constraints) if it does not
 * already exist, so this migration is safe to run both against a brand-new database (creates everything)
 * and against an existing eShop Plus production database (no-ops on tables that are already there,
 * letting Laravel's migrations table finally track reality instead of the empty/fabricated entries found
 * in the original app - see docs/PHASE_1_DATABASE_MIGRATION_PLAN.md "Migration bookkeeping" section).
 *
 * Engine (MyISAM/InnoDB) and column types (double vs DECIMAL) are reproduced AS-IS here. Phase 1's
 * InnoDB conversion and DECIMAL migration are separate, later migrations - see
 * 2025_01_02_000000_convert_myisam_tables_to_innodb.php and
 * 2025_01_03_000000_convert_money_columns_to_decimal.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('languages')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `languages` (
              `id` int(11) NOT NULL,
              `language` varchar(128) DEFAULT NULL,
              `code` varchar(8) DEFAULT NULL,
              `is_rtl` tinyint(4) NOT NULL DEFAULT 0,
              `native_language` varchar(256) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `languages`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `languages`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('currencies')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `currencies` (
              `id` int(11) NOT NULL,
              `name` varchar(256) DEFAULT NULL,
              `code` varchar(256) DEFAULT NULL,
              `symbol` varchar(256) DEFAULT NULL,
              `exchange_rate` varchar(256) DEFAULT NULL,
              `is_default` int(11) NOT NULL DEFAULT 0,
              `status` int(11) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `currencies`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `currencies`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('languages');
    }
};
