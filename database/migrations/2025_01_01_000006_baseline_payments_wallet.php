<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - payments_wallet domain.
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
        if (!Schema::hasTable('transactions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `transactions` (
              `id` int(11) NOT NULL,
              `transaction_type` varchar(16) NOT NULL,
              `user_id` int(11) NOT NULL,
              `order_id` varchar(128) DEFAULT NULL,
              `order_item_id` int(11) DEFAULT NULL,
              `type` varchar(64) DEFAULT NULL,
              `txn_id` varchar(256) DEFAULT NULL,
              `payu_txn_id` varchar(512) DEFAULT NULL,
              `amount` double NOT NULL,
              `status` varchar(12) DEFAULT NULL,
              `currency_code` varchar(5) DEFAULT NULL,
              `payer_email` varchar(64) DEFAULT NULL,
              `message` varchar(128) NOT NULL,
              `transaction_date` timestamp NULL DEFAULT current_timestamp(),
              `is_refund` tinyint(4) DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `transactions`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`),
              ADD KEY `order_id` (`order_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `transactions`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('wallet_transactions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `wallet_transactions` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `type` varchar(8) NOT NULL COMMENT 'credit | debit',
              `amount` double NOT NULL,
              `message` varchar(512) NOT NULL,
              `status` tinyint(4) NOT NULL,
              `date_created` timestamp NOT NULL DEFAULT current_timestamp(),
              `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `wallet_transactions`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `wallet_transactions`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('payment_requests')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `payment_requests` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `payment_type` varchar(56) NOT NULL,
              `payment_address` varchar(1024) NOT NULL,
              `amount_requested` decimal(10,2) DEFAULT NULL,
              `remarks` varchar(512) DEFAULT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `payment_requests`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `payment_requests`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('promo_codes')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `promo_codes` (
              `id` int(11) NOT NULL,
              `store_id` int(11) DEFAULT NULL,
              `title` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`title`)),
              `promo_code` varchar(28) NOT NULL,
              `message` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`message`)),
              `start_date` varchar(28) DEFAULT NULL,
              `end_date` varchar(28) DEFAULT NULL,
              `no_of_users` int(11) DEFAULT NULL,
              `minimum_order_amount` double DEFAULT NULL,
              `discount` double DEFAULT NULL,
              `discount_type` varchar(32) DEFAULT NULL,
              `max_discount_amount` double DEFAULT NULL,
              `repeat_usage` tinyint(4) NOT NULL,
              `no_of_repeat_usage` int(11) DEFAULT NULL,
              `image` varchar(256) DEFAULT NULL,
              `status` tinyint(4) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `is_cashback` tinyint(4) DEFAULT 0,
              `list_promocode` tinyint(4) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `promo_codes`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `promo_codes`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('payment_requests');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('transactions');
    }
};
