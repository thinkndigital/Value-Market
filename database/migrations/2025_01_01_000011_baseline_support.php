<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - support domain.
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
        if (!Schema::hasTable('tickets')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `tickets` (
              `id` int(11) NOT NULL,
              `ticket_type_id` int(11) DEFAULT NULL,
              `user_id` int(11) DEFAULT NULL,
              `subject` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `email` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `status` tinyint(4) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `tickets`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `tickets`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('ticket_messages')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `ticket_messages` (
              `id` int(11) NOT NULL,
              `user_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `user_id` int(11) DEFAULT NULL,
              `ticket_id` int(11) DEFAULT NULL,
              `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `attachments` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ticket_messages`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ticket_messages`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('ticket_types')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `ticket_types` (
              `id` int(11) NOT NULL,
              `title` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ticket_types`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ticket_types`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('ch_messages')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `ch_messages` (
              `id` char(36) NOT NULL,
              `from_id` bigint(20) NOT NULL,
              `to_id` bigint(20) NOT NULL,
              `body` varchar(5000) DEFAULT NULL,
              `attachment` varchar(255) DEFAULT NULL,
              `seen` tinyint(1) NOT NULL DEFAULT 0,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ch_messages`
              ADD PRIMARY KEY (`id`);
SQL);
        }

        if (!Schema::hasTable('ch_favorites')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `ch_favorites` (
              `id` char(36) NOT NULL,
              `user_id` bigint(20) NOT NULL,
              `favorite_id` bigint(20) NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `ch_favorites`
              ADD PRIMARY KEY (`id`);
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ch_favorites');
        Schema::dropIfExists('ch_messages');
        Schema::dropIfExists('ticket_types');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
