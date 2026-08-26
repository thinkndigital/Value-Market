<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - vendors domain.
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
        if (!Schema::hasTable('stores')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `stores` (
              `id` int(11) NOT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `slug` varchar(256) NOT NULL,
              `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`description`)),
              `image` varchar(256) DEFAULT NULL,
              `banner_image` varchar(256) DEFAULT NULL,
              `banner_image_for_most_selling_product` varchar(256) DEFAULT NULL,
              `stack_image` varchar(256) DEFAULT NULL,
              `login_image` varchar(256) DEFAULT NULL,
              `half_store_logo` varchar(256) DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `is_single_seller_order_system` tinyint(4) NOT NULL DEFAULT 0,
              `is_default_store` tinyint(4) DEFAULT NULL,
              `note_for_necessary_documents` varchar(2048) DEFAULT NULL,
              `primary_color` varchar(256) DEFAULT NULL,
              `secondary_color` varchar(256) DEFAULT NULL,
              `store_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`store_settings`)),
              `hover_color` varchar(256) DEFAULT NULL,
              `active_color` varchar(256) DEFAULT NULL,
              `background_color` varchar(256) NOT NULL,
              `status` int(11) DEFAULT NULL,
              `rating` double NOT NULL DEFAULT 0,
              `no_of_ratings` int(11) NOT NULL DEFAULT 0,
              `delivery_charge_type` varchar(256) NOT NULL,
              `delivery_charge_amount` int(11) NOT NULL DEFAULT 0,
              `minimum_free_delivery_amount` int(11) NOT NULL DEFAULT 0,
              `product_deliverability_type` varchar(256) NOT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `stores`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `stores`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('seller_data')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `seller_data` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `national_identity_card` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `authorized_signature` varchar(1028) DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `pan_number` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
              `status` tinyint(2) NOT NULL DEFAULT 2 COMMENT 'approved: 1 | not-approved: 2 | deactive:0 | removed :7',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_data`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_data`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('seller_store')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `seller_store` (
              `id` int(11) NOT NULL,
              `seller_id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `store_id` int(11) NOT NULL,
              `slug` varchar(256) NOT NULL,
              `category_ids` varchar(256) DEFAULT NULL,
              `store_name` varchar(256) NOT NULL,
              `store_description` varchar(256) NOT NULL,
              `logo` varchar(256) NOT NULL,
              `store_thumbnail` varchar(256) NOT NULL,
              `other_documents` mediumtext DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `store_url` varchar(256) NOT NULL,
              `no_of_ratings` int(11) NOT NULL DEFAULT 0,
              `rating` double NOT NULL DEFAULT 0,
              `bank_name` varchar(256) NOT NULL,
              `bank_code` varchar(256) NOT NULL,
              `account_name` varchar(256) NOT NULL,
              `account_number` varchar(256) NOT NULL,
              `address_proof` varchar(256) NOT NULL,
              `tax_name` varchar(256) NOT NULL,
              `tax_number` varchar(256) NOT NULL,
              `permissions` varchar(256) DEFAULT NULL,
              `commission` double NOT NULL DEFAULT 0,
              `latitude` varchar(256) DEFAULT NULL,
              `longitude` varchar(256) DEFAULT NULL,
              `city` int(11) DEFAULT NULL,
              `zipcode` int(11) DEFAULT NULL,
              `deliverable_type` int(11) NOT NULL DEFAULT 1,
              `deliverable_zones` varchar(2048) DEFAULT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'deactive: 0 | active: 1	',
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_store`
              ADD PRIMARY KEY (`id`),
              ADD KEY `seller_id` (`seller_id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_store`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_store`
              ADD CONSTRAINT `seller_id` FOREIGN KEY (`seller_id`) REFERENCES `seller_data` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
              ADD CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
SQL);
        }

        if (!Schema::hasTable('seller_commissions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `seller_commissions` (
              `id` int(11) NOT NULL,
              `seller_id` int(11) NOT NULL,
              `store_id` int(11) NOT NULL,
              `category_id` int(11) NOT NULL DEFAULT 0,
              `commission` double(10,2) NOT NULL DEFAULT 0.00,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_commissions`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `seller_commissions`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_commissions');
        Schema::dropIfExists('seller_store');
        Schema::dropIfExists('seller_data');
        Schema::dropIfExists('stores');
    }
};
