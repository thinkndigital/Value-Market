<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - engagement domain.
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
        if (!Schema::hasTable('favorites')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `favorites` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `product_id` int(11) DEFAULT NULL,
              `seller_id` int(11) DEFAULT NULL,
              `product_type` varchar(256) DEFAULT NULL,
              `is_seller` int(11) NOT NULL DEFAULT 0,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `favorites`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `product_id` (`product_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `favorites`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('search_history')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `search_history` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `search_term` varchar(2048) DEFAULT NULL,
              `clicks` int(11) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `search_history`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `search_history`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('notifications')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `notifications` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` varchar(128) NOT NULL,
              `message` varchar(512) NOT NULL,
              `type` varchar(12) NOT NULL,
              `type_id` text NOT NULL,
              `send_to` varchar(64) DEFAULT NULL,
              `users_id` text DEFAULT NULL,
              `image` varchar(128) DEFAULT NULL,
              `link` varchar(512) DEFAULT 'NULL',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `notifications`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `notifications`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('system_notification')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `system_notification` (
              `id` int(11) NOT NULL,
              `title` varchar(256) DEFAULT NULL,
              `message` varchar(20) DEFAULT NULL,
              `type` varchar(256) DEFAULT NULL,
              `type_id` int(11) DEFAULT 0,
              `read_by` tinyint(4) NOT NULL DEFAULT 0,
              `date_sent` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `system_notification`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `system_notification`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('user_fcm')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `user_fcm` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `fcm_id` varchar(1024) NOT NULL,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_fcm`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_fcm`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('user_client_preferences')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `user_client_preferences` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `user_id` varchar(56) NOT NULL,
              `table_name` varchar(255) NOT NULL,
              `visible_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`visible_columns`)),
              `default_view` varchar(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_client_preferences`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_client_preferences`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_client_preferences');
        Schema::dropIfExists('user_fcm');
        Schema::dropIfExists('system_notification');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('search_history');
        Schema::dropIfExists('favorites');
    }
};
