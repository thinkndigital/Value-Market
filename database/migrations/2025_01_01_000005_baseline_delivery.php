<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - delivery domain.
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
        if (!Schema::hasTable('delivery_boy_notifications')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `delivery_boy_notifications` (
              `id` int(11) NOT NULL,
              `delivery_boy_id` int(11) NOT NULL,
              `order_id` int(11) NOT NULL,
              `title` mediumtext NOT NULL,
              `message` mediumtext NOT NULL,
              `type` varchar(56) NOT NULL,
              `date_created` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `delivery_boy_notifications`
              ADD PRIMARY KEY (`id`),
              ADD KEY `delivery_boy_id` (`delivery_boy_id`),
              ADD KEY `order_id` (`order_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `delivery_boy_notifications`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('fund_transfers')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `fund_transfers` (
              `id` int(11) NOT NULL,
              `delivery_boy_id` int(11) NOT NULL,
              `opening_balance` double NOT NULL,
              `closing_balance` double NOT NULL,
              `amount` double NOT NULL,
              `status` varchar(28) DEFAULT NULL,
              `message` varchar(512) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `fund_transfers`
              ADD PRIMARY KEY (`id`),
              ADD KEY `delivery_boy_id` (`delivery_boy_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `fund_transfers`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
        Schema::dropIfExists('delivery_boy_notifications');
    }
};
