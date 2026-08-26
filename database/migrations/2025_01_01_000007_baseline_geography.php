<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - geography domain.
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
        if (!Schema::hasTable('countries')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `countries` (
              `id` mediumint(8) UNSIGNED NOT NULL,
              `name` varchar(100) NOT NULL,
              `iso3` char(3) DEFAULT NULL,
              `numeric_code` char(3) DEFAULT NULL,
              `iso2` char(2) DEFAULT NULL,
              `phonecode` varchar(255) DEFAULT NULL,
              `capital` varchar(255) DEFAULT NULL,
              `currency` varchar(255) DEFAULT NULL,
              `currency_name` varchar(255) DEFAULT NULL,
              `currency_symbol` varchar(255) DEFAULT NULL,
              `tld` varchar(255) DEFAULT NULL,
              `native` varchar(255) DEFAULT NULL,
              `region` varchar(255) DEFAULT NULL,
              `subregion` varchar(255) DEFAULT NULL,
              `timezones` text DEFAULT NULL,
              `translations` text DEFAULT NULL,
              `latitude` decimal(10,8) DEFAULT NULL,
              `longitude` decimal(11,8) DEFAULT NULL,
              `emoji` varchar(191) DEFAULT NULL,
              `emojiU` varchar(191) DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `flag` tinyint(1) NOT NULL DEFAULT 1,
              `wikiDataId` varchar(255) DEFAULT NULL COMMENT 'Rapid API GeoDB Cities'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `countries`
              ADD PRIMARY KEY (`id`),
              ADD KEY `name` (`name`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `countries`
              MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('cities')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `cities` (
              `id` int(11) NOT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `minimum_free_delivery_order_amount` double NOT NULL DEFAULT 0,
              `delivery_charges` double NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cities`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cities`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('areas')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `areas` (
              `id` int(11) NOT NULL,
              `name` mediumtext NOT NULL,
              `city_id` int(11) NOT NULL,
              `zipcode_id` int(11) NOT NULL DEFAULT 0,
              `minimum_free_delivery_order_amount` double NOT NULL DEFAULT 100,
              `delivery_charges` double DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `areas`
              ADD PRIMARY KEY (`id`),
              ADD KEY `city_id` (`city_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `areas`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('zipcodes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `zipcodes` (
              `id` int(11) NOT NULL,
              `zipcode` varchar(512) DEFAULT NULL,
              `city_id` int(11) NOT NULL,
              `minimum_free_delivery_order_amount` double NOT NULL DEFAULT 0,
              `delivery_charges` double DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `zipcodes`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `zipcodes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('zones')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `zones` (
              `id` int(11) NOT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `serviceable_city_ids` longtext DEFAULT NULL,
              `serviceable_zipcode_ids` longtext DEFAULT NULL,
              `status` int(11) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `zones`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `zones`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('addresses')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `addresses` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `name` varchar(64) DEFAULT NULL,
              `type` varchar(32) DEFAULT NULL,
              `mobile` varchar(24) DEFAULT NULL,
              `alternate_mobile` varchar(24) DEFAULT NULL,
              `address` mediumtext DEFAULT NULL,
              `landmark` varchar(128) DEFAULT NULL,
              `area_id` int(11) DEFAULT NULL,
              `city_id` int(11) DEFAULT NULL,
              `city` varchar(256) NOT NULL DEFAULT 'NULL',
              `area` varchar(256) NOT NULL DEFAULT 'NULL',
              `pincode` varchar(256) DEFAULT NULL,
              `system_pincode` tinyint(4) NOT NULL DEFAULT 1,
              `country_code` int(11) DEFAULT NULL,
              `state` varchar(64) DEFAULT NULL,
              `country` varchar(64) DEFAULT NULL,
              `latitude` varchar(64) DEFAULT NULL,
              `longitude` varchar(64) DEFAULT NULL,
              `is_default` int(11) NOT NULL DEFAULT 0,
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `created_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `addresses`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `addresses`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('pickup_locations')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `pickup_locations` (
              `id` int(11) NOT NULL,
              `seller_id` int(11) NOT NULL,
              `pickup_location` varchar(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `name` varchar(512) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `email` varchar(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `phone` varchar(28) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `address` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `address2` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
              `city` varchar(56) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `state` varchar(56) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `country` varchar(56) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `pincode` varchar(56) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
              `latitude` varchar(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
              `longitude` varchar(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `pickup_locations`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `pickup_locations`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pickup_locations');
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('zones');
        Schema::dropIfExists('zipcodes');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
