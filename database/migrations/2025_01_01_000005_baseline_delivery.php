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
 * Column types (double vs DECIMAL) are reproduced AS-IS here; Phase 1's DECIMAL migration is a
 * separate, later migration - see 2025_01_03_000000_convert_money_columns_to_decimal.php.
 *
 * Storage engine: tables that shipped as MyISAM in the original dump are created here directly as
 * InnoDB instead of reproduced AS-IS - Cloud SQL for MySQL refuses to create MyISAM tables outright
 * ("Storage engine MyISAM is disabled (Table creation is disallowed)"), so AS-IS reproduction is not
 * an option on that platform. 2025_01_02_000000_convert_myisam_tables_to_innodb.php (originally written
 * to convert these tables after an AS-IS MyISAM create) is kept as a harmless no-op for that reason - it
 * only ALTERs a table's engine when it isn't already InnoDB, which is never true anymore now that these
 * tables are created as InnoDB from the start.
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
