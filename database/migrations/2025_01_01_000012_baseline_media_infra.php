<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - media_infra domain.
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
        if (!Schema::hasTable('images')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `images` (
              `id` int(11) NOT NULL,
              `uuid` varchar(256) DEFAULT NULL,
              `seller_id` int(11) NOT NULL DEFAULT 0,
              `name` mediumtext DEFAULT NULL,
              `file_name` mediumtext DEFAULT NULL,
              `disk` varchar(256) DEFAULT NULL,
              `disk_name` varchar(256) DEFAULT NULL,
              `conversions_disk` varchar(256) DEFAULT NULL,
              `collection_name` varchar(256) DEFAULT NULL,
              `extension` varchar(16) DEFAULT NULL,
              `mime_type` varchar(16) DEFAULT NULL,
              `custom_properties` mediumtext DEFAULT NULL,
              `size` mediumtext DEFAULT NULL,
              `generated_conversions` varchar(256) DEFAULT NULL,
              `responsive_images` varchar(256) DEFAULT NULL,
              `manipulations` varchar(256) DEFAULT NULL,
              `order_column` int(11) DEFAULT NULL,
              `model_type` varchar(256) DEFAULT NULL,
              `model_id` bigint(8) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `_token` varchar(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `images`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `images`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('media')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `media` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `seller_id` int(11) NOT NULL DEFAULT 0,
              `name` mediumtext NOT NULL,
              `extension` varchar(16) NOT NULL,
              `type` varchar(16) NOT NULL,
              `sub_directory` mediumtext NOT NULL,
              `size` mediumtext NOT NULL,
              `order_column` int(11) DEFAULT NULL,
              `model_type` varchar(256) NOT NULL,
              `model_id` int(11) DEFAULT NULL,
              `file_name` varchar(256) NOT NULL,
              `disk` varchar(256) NOT NULL,
              `conversions_disk` varchar(256) NOT NULL,
              `collection_name` varchar(256) NOT NULL,
              `mime_type` varchar(256) NOT NULL,
              `custom_properties` varchar(256) NOT NULL,
              `generated_conversions` varchar(256) NOT NULL,
              `responsive_images` varchar(256) NOT NULL,
              `manipulations` varchar(256) NOT NULL,
              `uuid` int(11) DEFAULT NULL,
              `object_url` varchar(1024) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `media`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `media`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('storage_types')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `storage_types` (
              `id` int(11) NOT NULL,
              `name` varchar(256) DEFAULT NULL,
              `is_default` tinyint(4) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `storage_types`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `storage_types`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('failed_jobs')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `failed_jobs` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `uuid` varchar(255) NOT NULL,
              `connection` text NOT NULL,
              `queue` text NOT NULL,
              `payload` longtext NOT NULL,
              `exception` longtext NOT NULL,
              `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `failed_jobs`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `failed_jobs`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('updates')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `updates` (
              `id` int(11) NOT NULL,
              `version` varchar(32) NOT NULL,
              `created_at` datetime NOT NULL,
              `updated_at` datetime NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `updates`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `updates`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('settings')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `settings` (
              `id` int(11) NOT NULL,
              `variable` varchar(128) NOT NULL,
              `value` mediumtext CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `settings`
              ADD PRIMARY KEY (`id`),
              ADD KEY `variable` (`variable`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `settings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('updates');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('storage_types');
        Schema::dropIfExists('media');
        Schema::dropIfExists('images');
    }
};
