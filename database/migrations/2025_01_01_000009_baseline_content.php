<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - content domain.
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
        if (!Schema::hasTable('sliders')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `sliders` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `type` varchar(16) NOT NULL,
              `type_id` int(11) DEFAULT 0,
              `link` varchar(512) DEFAULT 'NULL',
              `image` varchar(256) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `sliders`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `sliders`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('offers')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `offers` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `type` varchar(32) DEFAULT NULL,
              `type_id` int(11) DEFAULT 0,
              `link` varchar(512) NOT NULL DEFAULT 'NULL',
              `image` varchar(256) NOT NULL,
              `banner_image` varchar(256) DEFAULT NULL,
              `min_discount` int(11) DEFAULT NULL,
              `max_discount` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `offers`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `offers`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('offer_sliders')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `offer_sliders` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `banner_image` varchar(256) DEFAULT NULL,
              `offer_ids` varchar(256) DEFAULT NULL,
              `status` int(11) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `offer_sliders`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `offer_sliders`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('sections')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `sections` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `short_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`short_description`)),
              `style` varchar(16) NOT NULL,
              `header_style` varchar(256) DEFAULT NULL,
              `product_ids` varchar(1024) DEFAULT NULL,
              `row_order` int(11) NOT NULL DEFAULT 0,
              `categories` mediumtext DEFAULT NULL,
              `product_type` varchar(1024) DEFAULT NULL,
              `banner_image` varchar(256) DEFAULT NULL,
              `background_color` varchar(256) DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `sections`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `sections`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('blogs')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `blogs` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `category_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `description` mediumtext DEFAULT NULL,
              `image` varchar(256) DEFAULT NULL,
              `slug` varchar(256) DEFAULT NULL,
              `status` tinyint(4) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `blogs`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `blogs`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('blog_categories')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `blog_categories` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `slug` varchar(256) DEFAULT NULL,
              `image` text NOT NULL,
              `banner` text DEFAULT NULL,
              `status` tinyint(4) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `blog_categories`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `blog_categories`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('faqs')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `faqs` (
              `id` int(11) NOT NULL,
              `question` mediumtext DEFAULT NULL,
              `answer` mediumtext DEFAULT NULL,
              `status` char(1) DEFAULT '1',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `faqs`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `faqs`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('themes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `themes` (
              `id` int(11) NOT NULL,
              `name` varchar(32) NOT NULL,
              `slug` varchar(32) NOT NULL,
              `image` varchar(512) DEFAULT NULL,
              `is_default` tinyint(4) NOT NULL DEFAULT 0,
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `created_on` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `themes`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `themes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('custom_messages')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `custom_messages` (
              `id` int(11) NOT NULL,
              `title` varchar(2048) DEFAULT NULL,
              `message` varchar(4096) DEFAULT NULL,
              `type` varchar(64) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `custom_messages`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `custom_messages`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('time_slots')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `time_slots` (
              `id` int(11) NOT NULL,
              `title` varchar(256) NOT NULL,
              `from_time` time NOT NULL,
              `to_time` time NOT NULL,
              `last_order_time` time NOT NULL,
              `status` tinyint(4) NOT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `time_slots`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `time_slots`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
        Schema::dropIfExists('custom_messages');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('blog_categories');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('offer_sliders');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('sliders');
    }
};
