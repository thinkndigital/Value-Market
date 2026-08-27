<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - commerce domain.
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
        if (!Schema::hasTable('cart')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `cart` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `product_variant_id` int(11) NOT NULL,
              `qty` int(11) NOT NULL,
              `is_saved_for_later` int(11) NOT NULL DEFAULT 0,
              `product_type` varchar(256) NOT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cart`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `product_variant_id` (`product_variant_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cart`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('cart_reminders')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `cart_reminders` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `user_id` bigint(20) UNSIGNED NOT NULL,
              `product_variant_id` bigint(20) UNSIGNED NOT NULL,
              `reminded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cart_reminders`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `cart_reminders_user_product_unique` (`user_id`,`product_variant_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `cart_reminders`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('orders')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `orders` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `address_id` int(11) DEFAULT NULL,
              `mobile` varchar(12) NOT NULL,
              `total` double NOT NULL,
              `delivery_charge` double DEFAULT 0,
              `is_delivery_charge_returnable` tinyint(4) NOT NULL DEFAULT 0,
              `wallet_balance` double DEFAULT 0,
              `promo_code_id` varchar(28) DEFAULT NULL,
              `promo_discount` double DEFAULT NULL,
              `discount` double DEFAULT 0,
              `total_payable` double DEFAULT NULL,
              `final_total` double DEFAULT NULL,
              `payment_method` varchar(16) NOT NULL,
              `latitude` varchar(256) DEFAULT NULL,
              `longitude` varchar(256) DEFAULT NULL,
              `address` mediumtext DEFAULT NULL,
              `delivery_time` varchar(128) DEFAULT NULL,
              `delivery_date` date DEFAULT NULL,
              `otp` int(11) DEFAULT 0,
              `email` varchar(254) DEFAULT 'NULL',
              `notes` varchar(512) DEFAULT NULL,
              `is_pos_order` tinyint(4) NOT NULL DEFAULT 0,
              `is_shiprocket_order` int(11) NOT NULL DEFAULT 0,
              `is_cod_collected` int(11) NOT NULL DEFAULT 0,
              `type` varchar(256) DEFAULT NULL,
              `order_payment_currency_id` int(11) NOT NULL,
              `order_payment_currency_code` varchar(128) NOT NULL,
              `base_currency_code` varchar(128) NOT NULL COMMENT 'The base currency used in the system when placing orders. ',
              `order_payment_currency_conversion_rate` double NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `orders`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `orders`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('order_items')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `order_items` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `order_id` int(11) NOT NULL,
              `delivery_boy_id` int(11) DEFAULT NULL,
              `seller_id` int(11) NOT NULL,
              `is_credited` tinyint(2) NOT NULL DEFAULT 0,
              `otp` int(11) NOT NULL DEFAULT 0,
              `product_name` varchar(512) DEFAULT NULL,
              `variant_name` varchar(256) DEFAULT NULL,
              `product_variant_id` int(11) NOT NULL,
              `quantity` int(11) NOT NULL,
              `delivered_quantity` int(11) NOT NULL DEFAULT 0,
              `price` double NOT NULL,
              `discounted_price` double DEFAULT NULL,
              `tax_ids` varchar(256) DEFAULT NULL,
              `tax_percent` double DEFAULT NULL,
              `tax_amount` double DEFAULT NULL,
              `discount` double DEFAULT 0,
              `sub_total` double NOT NULL,
              `deliver_by` varchar(128) DEFAULT NULL,
              `updated_by` int(11) DEFAULT 0,
              `status` varchar(1024) NOT NULL,
              `admin_commission_amount` double NOT NULL DEFAULT 0,
              `seller_commission_amount` double NOT NULL DEFAULT 0,
              `active_status` varchar(1024) DEFAULT NULL,
              `hash_link` varchar(512) DEFAULT 'NULL',
              `is_sent` tinyint(4) DEFAULT 0,
              `order_type` varchar(256) NOT NULL,
              `attachment` varchar(2048) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_items`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `order_id` (`order_id`),
              ADD KEY `product_variant_id` (`product_variant_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_items`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('order_charges')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `order_charges` (
              `id` int(11) NOT NULL,
              `seller_id` int(11) NOT NULL,
              `product_variant_ids` varchar(1024) NOT NULL,
              `order_id` int(11) NOT NULL,
              `order_item_ids` varchar(1024) NOT NULL,
              `delivery_charge` double DEFAULT NULL,
              `promo_code_id` varchar(1024) DEFAULT 'NULL',
              `promo_discount` double DEFAULT NULL,
              `sub_total` double DEFAULT NULL,
              `total` double DEFAULT NULL,
              `otp` int(11) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_charges`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_charges`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('order_trackings')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `order_trackings` (
              `id` int(11) NOT NULL,
              `order_id` int(11) NOT NULL,
              `shiprocket_order_id` int(11) NOT NULL,
              `shipment_id` int(11) NOT NULL,
              `courier_company_id` int(11) NOT NULL DEFAULT 0,
              `awb_code` varchar(128) NOT NULL DEFAULT 'NULL',
              `pickup_status` int(11) NOT NULL,
              `pickup_scheduled_date` varchar(255) NOT NULL,
              `pickup_token_number` varchar(255) NOT NULL,
              `status` int(11) NOT NULL,
              `others` varchar(255) NOT NULL,
              `pickup_generated_date` varchar(255) NOT NULL,
              `data` varchar(255) NOT NULL,
              `date` varchar(255) NOT NULL,
              `is_canceled` int(11) NOT NULL DEFAULT 0,
              `manifest_url` varchar(512) NOT NULL,
              `label_url` varchar(512) NOT NULL,
              `invoice_url` varchar(512) NOT NULL,
              `order_item_id` mediumtext DEFAULT NULL,
              `courier_agency` varchar(20) DEFAULT NULL,
              `tracking_id` varchar(120) NOT NULL,
              `parcel_id` int(11) DEFAULT NULL,
              `url` varchar(256) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_trackings`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_trackings`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('order_bank_transfers')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `order_bank_transfers` (
              `id` int(11) NOT NULL,
              `order_id` int(11) NOT NULL DEFAULT 0,
              `attachments` varchar(512) DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `status` tinyint(2) DEFAULT 0 COMMENT '(0:pending|1:rejected|2:accepted)',
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_bank_transfers`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `order_bank_transfers`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('return_requests')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `return_requests` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `product_id` int(11) NOT NULL,
              `product_variant_id` int(11) NOT NULL,
              `order_id` int(11) NOT NULL,
              `order_item_id` int(11) NOT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `remarks` varchar(1024) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `return_requests`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `product_id` (`product_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `return_requests`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('parcels')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `parcels` (
              `id` int(11) UNSIGNED NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `order_id` int(11) NOT NULL,
              `delivery_boy_id` int(11) DEFAULT NULL,
              `name` varchar(255) NOT NULL,
              `type` varchar(256) DEFAULT NULL,
              `status` varchar(1024) NOT NULL,
              `active_status` varchar(1024) NOT NULL,
              `otp` int(6) NOT NULL,
              `delivery_charge` double NOT NULL DEFAULT 0,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `parcels`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `parcels`
              MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('parcel_items')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `parcel_items` (
              `id` int(11) UNSIGNED NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `parcel_id` int(11) NOT NULL,
              `order_item_id` int(11) NOT NULL,
              `product_variant_id` int(11) NOT NULL,
              `unit_price` double NOT NULL,
              `quantity` int(11) NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `parcel_items`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `parcel_items`
              MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('digital_orders_mails')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `digital_orders_mails` (
              `id` int(11) NOT NULL,
              `order_id` int(11) DEFAULT NULL,
              `order_item_id` int(11) DEFAULT NULL,
              `subject` varchar(256) DEFAULT NULL,
              `message` varchar(256) DEFAULT NULL,
              `file_url` varchar(512) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `digital_orders_mails`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `digital_orders_mails`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_orders_mails');
        Schema::dropIfExists('parcel_items');
        Schema::dropIfExists('parcels');
        Schema::dropIfExists('return_requests');
        Schema::dropIfExists('order_bank_transfers');
        Schema::dropIfExists('order_trackings');
        Schema::dropIfExists('order_charges');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_reminders');
        Schema::dropIfExists('cart');
    }
};
