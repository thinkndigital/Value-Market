<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - catalog domain.
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
        if (!Schema::hasTable('categories')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `categories` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `parent_id` int(11) DEFAULT NULL,
              `slug` varchar(256) NOT NULL,
              `image` varchar(256) NOT NULL,
              `banner` varchar(256) NOT NULL,
              `style` varchar(256) DEFAULT NULL,
              `row_order` int(11) DEFAULT 0,
              `status` tinyint(4) DEFAULT NULL,
              `clicks` int(11) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `categories`
              ADD PRIMARY KEY (`id`),
              ADD KEY `parent_id` (`parent_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `categories`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('category_sliders')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `category_sliders` (
              `id` int(11) NOT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `category_ids` varchar(256) DEFAULT NULL,
              `store_id` int(11) DEFAULT NULL,
              `style` varchar(256) DEFAULT NULL,
              `status` int(11) DEFAULT NULL,
              `created_at` datetime NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `banner_image` varchar(256) DEFAULT NULL,
              `background_color` varchar(256) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `category_sliders`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `category_sliders`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('brands')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `brands` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `slug` varchar(256) DEFAULT NULL,
              `image` text NOT NULL,
              `status` tinyint(4) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `brands`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `brands`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('attributes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `attributes` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `category_id` int(11) DEFAULT NULL,
              `name` varchar(256) NOT NULL,
              `type` varchar(64) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `attributes`
              ADD PRIMARY KEY (`id`),
              ADD KEY `category_id` (`category_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `attributes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('attribute_values')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `attribute_values` (
              `id` int(11) NOT NULL,
              `attribute_id` int(11) NOT NULL,
              `filterable` int(11) DEFAULT 0,
              `value` varchar(1024) NOT NULL,
              `swatche_type` int(11) DEFAULT 0,
              `swatche_value` varchar(512) DEFAULT NULL,
              `status` tinyint(4) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `attribute_values`
              ADD PRIMARY KEY (`id`),
              ADD KEY `attribute_id` (`attribute_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `attribute_values`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('taxes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `taxes` (
              `id` int(11) NOT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `percentage` mediumtext NOT NULL,
              `status` tinyint(2) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `taxes`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `taxes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('products')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `products` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `product_identity` varchar(50) DEFAULT NULL,
              `category_id` int(11) NOT NULL,
              `seller_id` int(11) DEFAULT NULL,
              `tax` varchar(256) DEFAULT NULL,
              `row_order` int(11) DEFAULT 0,
              `type` varchar(34) DEFAULT NULL,
              `stock_type` varchar(64) DEFAULT NULL COMMENT '0 =>''Simple_Product_Stock_Active'' 1 => "Product_Level" 2 => "Variable_Level"',
              `name` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`name`)),
              `short_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`short_description`)),
              `slug` varchar(512) NOT NULL,
              `indicator` tinyint(4) DEFAULT NULL COMMENT '0 - none | 1 - veg | 2 - non-veg',
              `cod_allowed` int(11) NOT NULL DEFAULT 1,
              `download_allowed` int(11) NOT NULL DEFAULT 0,
              `download_type` varchar(40) DEFAULT NULL,
              `download_link` varchar(512) DEFAULT NULL,
              `minimum_order_quantity` int(11) NOT NULL DEFAULT 1,
              `quantity_step_size` int(11) NOT NULL DEFAULT 1,
              `total_allowed_quantity` int(11) DEFAULT NULL,
              `is_prices_inclusive_tax` int(11) NOT NULL DEFAULT 0,
              `is_returnable` int(11) DEFAULT 0,
              `is_cancelable` int(11) DEFAULT 0,
              `cancelable_till` varchar(32) DEFAULT NULL,
              `is_attachment_required` int(11) NOT NULL DEFAULT 0,
              `image` mediumtext NOT NULL,
              `other_images` mediumtext DEFAULT NULL,
              `video_type` varchar(32) DEFAULT NULL,
              `video` varchar(512) DEFAULT NULL,
              `tags` text DEFAULT NULL,
              `warranty_period` varchar(32) DEFAULT NULL,
              `guarantee_period` varchar(32) DEFAULT NULL,
              `made_in` varchar(128) DEFAULT NULL,
              `hsn_code` varchar(256) DEFAULT NULL,
              `brand` varchar(256) DEFAULT NULL,
              `sku` varchar(128) DEFAULT NULL,
              `stock` int(11) DEFAULT NULL,
              `availability` tinyint(4) DEFAULT NULL,
              `rating` double DEFAULT 0,
              `no_of_ratings` int(11) DEFAULT 0,
              `description` mediumtext DEFAULT NULL,
              `extra_description` varchar(2048) NOT NULL DEFAULT 'NULL',
              `deliverable_type` int(11) NOT NULL DEFAULT 1 COMMENT '(0:none, 1:all, 2:include, 3:exclude)',
              `deliverable_zipcodes` varchar(512) DEFAULT NULL,
              `city_deliverable_type` int(11) NOT NULL DEFAULT 1 COMMENT '	(0:none, 1:all, 2:include, 3:exclude)',
              `deliverable_cities` varchar(256) NOT NULL,
              `deliverable_zones` varchar(256) DEFAULT NULL,
              `pickup_location` varchar(512) DEFAULT NULL,
              `status` int(2) DEFAULT 1,
              `minimum_free_delivery_order_qty` int(11) NOT NULL DEFAULT 0,
              `delivery_charges` double NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `products`
              ADD PRIMARY KEY (`id`),
              ADD KEY `category_id` (`category_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `products`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('product_variants')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `product_variants` (
              `id` int(11) NOT NULL,
              `product_id` int(11) NOT NULL,
              `attribute_value_ids` text DEFAULT NULL,
              `attribute_set` varchar(1024) DEFAULT NULL,
              `price` double NOT NULL,
              `special_price` double DEFAULT 0,
              `sku` varchar(128) DEFAULT NULL,
              `stock` int(11) DEFAULT NULL,
              `weight` float NOT NULL DEFAULT 0,
              `height` float NOT NULL DEFAULT 0,
              `breadth` float NOT NULL DEFAULT 0,
              `length` float NOT NULL DEFAULT 0,
              `images` text DEFAULT NULL,
              `availability` tinyint(4) DEFAULT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_variants`
              ADD PRIMARY KEY (`id`),
              ADD KEY `product_id` (`product_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_variants`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('product_attributes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `product_attributes` (
              `id` int(11) NOT NULL,
              `product_id` int(11) NOT NULL,
              `attribute_value_ids` text NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_attributes`
              ADD PRIMARY KEY (`id`),
              ADD KEY `product_id` (`product_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_attributes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('custom_fields')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `custom_fields` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `store_id` bigint(20) UNSIGNED NOT NULL,
              `name` varchar(255) NOT NULL,
              `type` enum('text','number','file','radio','dropdown','checkbox','date','textarea','color') NOT NULL,
              `field_length` int(11) DEFAULT NULL,
              `min` int(11) DEFAULT NULL,
              `max` int(11) DEFAULT NULL,
              `required` tinyint(1) NOT NULL DEFAULT 0,
              `active` tinyint(1) NOT NULL DEFAULT 1,
              `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`options`)),
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `custom_fields`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `custom_fields`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('product_custom_field_values')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `product_custom_field_values` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `product_id` bigint(20) UNSIGNED NOT NULL,
              `custom_field_id` bigint(20) UNSIGNED NOT NULL,
              `value` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_custom_field_values`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_custom_field_values`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('product_faqs')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `product_faqs` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `seller_id` int(11) DEFAULT NULL,
              `product_id` int(11) DEFAULT NULL,
              `votes` int(11) NOT NULL DEFAULT 0,
              `question` text DEFAULT NULL,
              `answer` text DEFAULT NULL,
              `answered_by` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_faqs`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_faqs`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('product_ratings')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `product_ratings` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `product_id` int(11) NOT NULL,
              `rating` double NOT NULL DEFAULT 0,
              `images` mediumtext DEFAULT NULL,
              `title` varchar(256) DEFAULT NULL,
              `comment` varchar(1024) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_ratings`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `product_id` (`product_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `product_ratings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_products')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_products` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `slug` varchar(256) DEFAULT NULL,
              `short_description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`short_description`)),
              `description` varchar(2048) DEFAULT NULL,
              `image` varchar(256) DEFAULT NULL,
              `seller_id` int(11) DEFAULT NULL,
              `product_type` varchar(256) DEFAULT NULL,
              `product_ids` varchar(256) DEFAULT NULL,
              `product_variant_ids` varchar(256) DEFAULT NULL,
              `price` double DEFAULT NULL,
              `special_price` double DEFAULT NULL,
              `attribute` varchar(256) DEFAULT NULL,
              `attribute_value_ids` varchar(256) DEFAULT NULL,
              `deliverable_type` int(11) DEFAULT NULL COMMENT ' (0:none, 1:all, 2:include, 3:exclude) ',
              `deliverable_zipcodes` varchar(256) DEFAULT NULL,
              `city_deliverable_type` int(11) NOT NULL DEFAULT 1 COMMENT ' (0:none, 1:all, 2:include, 3:exclude)',
              `deliverable_cities` varchar(256) NOT NULL,
              `deliverable_zones` varchar(256) DEFAULT NULL,
              `pickup_location` varchar(256) DEFAULT NULL,
              `other_images` varchar(526) DEFAULT NULL,
              `tax` varchar(256) DEFAULT NULL,
              `tags` varchar(256) DEFAULT NULL,
              `selected_products` int(11) DEFAULT NULL,
              `sku` varchar(256) DEFAULT NULL,
              `stock` varchar(256) DEFAULT NULL,
              `availability` int(11) DEFAULT NULL,
              `cod_allowed` int(11) DEFAULT NULL,
              `download_allowed` int(11) NOT NULL DEFAULT 0,
              `download_type` varchar(256) DEFAULT NULL,
              `download_link` varchar(256) DEFAULT NULL,
              `is_prices_inclusive_tax` int(11) DEFAULT NULL,
              `is_returnable` int(11) DEFAULT NULL,
              `is_cancelable` int(11) DEFAULT NULL,
              `cancelable_till` varchar(48) DEFAULT NULL,
              `is_attachment_required` int(11) NOT NULL DEFAULT 0,
              `weight` varchar(256) DEFAULT NULL,
              `height` varchar(256) DEFAULT NULL,
              `length` varchar(256) DEFAULT NULL,
              `breadth` varchar(256) DEFAULT NULL,
              `total_allowed_quantity` int(11) DEFAULT NULL,
              `minimum_order_quantity` int(11) DEFAULT NULL,
              `quantity_step_size` int(11) DEFAULT NULL,
              `has_similar_product` int(11) DEFAULT 0,
              `similar_product_ids` varchar(256) DEFAULT NULL,
              `status` int(11) NOT NULL DEFAULT 1,
              `minimum_free_delivery_order_qty` int(11) NOT NULL DEFAULT 0,
              `delivery_charges` varchar(256) DEFAULT NULL,
              `rating` double NOT NULL DEFAULT 0,
              `no_of_ratings` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_products`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_products`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_product_attributes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_product_attributes` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `name` varchar(256) DEFAULT NULL,
              `status` int(11) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_attributes`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_attributes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_product_attribute_values')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_product_attribute_values` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `combo_product_attribute_id` int(11) DEFAULT NULL,
              `value` varchar(256) DEFAULT NULL,
              `status` int(11) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_attribute_values`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_attribute_values`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_product_custom_field_values')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_product_custom_field_values` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `product_id` bigint(20) UNSIGNED NOT NULL,
              `custom_field_id` bigint(20) UNSIGNED NOT NULL,
              `value` text DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_custom_field_values`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_custom_field_values`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_product_faqs')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_product_faqs` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `seller_id` int(11) DEFAULT NULL,
              `product_id` int(11) DEFAULT NULL,
              `votes` int(11) NOT NULL DEFAULT 0,
              `question` varchar(256) DEFAULT NULL,
              `answer` varchar(256) DEFAULT NULL,
              `answered_by` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_faqs`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_faqs`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('combo_product_ratings')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `combo_product_ratings` (
              `id` int(11) NOT NULL,
              `user_id` int(11) DEFAULT NULL,
              `product_id` int(11) DEFAULT NULL,
              `rating` int(11) NOT NULL DEFAULT 0,
              `images` varchar(2048) DEFAULT NULL,
              `title` varchar(256) DEFAULT NULL,
              `comment` varchar(2048) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_ratings`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `combo_product_ratings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_product_ratings');
        Schema::dropIfExists('combo_product_faqs');
        Schema::dropIfExists('combo_product_custom_field_values');
        Schema::dropIfExists('combo_product_attribute_values');
        Schema::dropIfExists('combo_product_attributes');
        Schema::dropIfExists('combo_products');
        Schema::dropIfExists('product_ratings');
        Schema::dropIfExists('product_faqs');
        Schema::dropIfExists('product_custom_field_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('category_sliders');
        Schema::dropIfExists('categories');
    }
};
