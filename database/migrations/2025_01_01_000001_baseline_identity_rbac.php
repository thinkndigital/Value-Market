<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Baseline migration - identity_rbac domain.
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
        if (!Schema::hasTable('users')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `users` (
              `id` int(11) NOT NULL,
              `role_id` int(11) DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `username` varchar(100) NOT NULL,
              `password` varchar(255) NOT NULL,
              `email` varchar(254) DEFAULT NULL,
              `mobile` varchar(20) DEFAULT NULL,
              `image` text DEFAULT NULL,
              `disk` varchar(256) NOT NULL,
              `balance` double DEFAULT 0,
              `activation_selector` varchar(255) DEFAULT NULL,
              `activation_code` varchar(255) DEFAULT NULL,
              `forgotten_password_selector` varchar(255) DEFAULT NULL,
              `forgotten_password_code` varchar(255) DEFAULT NULL,
              `forgotten_password_time` int(11) DEFAULT NULL,
              `remember_selector` varchar(255) DEFAULT NULL,
              `remember_token` varchar(255) DEFAULT NULL,
              `created_on` int(11) UNSIGNED DEFAULT NULL,
              `last_login` int(11) UNSIGNED DEFAULT NULL,
              `active` tinyint(1) UNSIGNED DEFAULT NULL,
              `company` varchar(100) DEFAULT NULL,
              `address` varchar(255) DEFAULT NULL,
              `bonus_type` varchar(30) DEFAULT 'percentage_per_order_item',
              `bonus` int(11) DEFAULT NULL,
              `cash_received` double(15,2) NOT NULL DEFAULT 0.00,
              `dob` varchar(16) DEFAULT NULL,
              `country_code` int(11) DEFAULT NULL,
              `city` text DEFAULT NULL,
              `area` text DEFAULT NULL,
              `street` text DEFAULT NULL,
              `pincode` varchar(32) DEFAULT NULL,
              `serviceable_zipcodes` varchar(256) DEFAULT NULL,
              `serviceable_cities` varchar(256) NOT NULL,
              `serviceable_zones` varchar(256) DEFAULT NULL,
              `apikey` varchar(32) DEFAULT NULL,
              `referral_code` varchar(32) DEFAULT NULL,
              `friends_code` varchar(28) DEFAULT NULL,
              `fcm_id` text DEFAULT NULL,
              `latitude` varchar(64) DEFAULT NULL,
              `longitude` varchar(64) DEFAULT NULL,
              `type` varchar(1024) NOT NULL DEFAULT 'phone',
              `front_licence_image` varchar(1024) DEFAULT NULL,
              `back_licence_image` varchar(1028) DEFAULT NULL,
              `status` tinyint(4) NOT NULL DEFAULT 0,
              `is_notification_on` int(11) NOT NULL DEFAULT 1,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `active_status` tinyint(1) NOT NULL DEFAULT 0,
              `avatar` varchar(255) NOT NULL DEFAULT 'avatar.png',
              `dark_mode` tinyint(1) NOT NULL DEFAULT 0,
              `messenger_color` varchar(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `users`
              ADD PRIMARY KEY (`id`),
              ADD KEY `mobile` (`mobile`),
              ADD KEY `email` (`email`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `users`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('roles')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `roles` (
              `id` mediumint(8) UNSIGNED NOT NULL,
              `name` varchar(20) NOT NULL,
              `description` varchar(100) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `roles`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `roles`
              MODIFY `id` mediumint(8) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('permissions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `permissions` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `name` varchar(255) NOT NULL,
              `guard_name` varchar(255) NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `permissions`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `permissions`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('model_has_roles')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `model_has_roles` (
              `role_id` bigint(20) UNSIGNED NOT NULL,
              `model_type` varchar(255) NOT NULL,
              `model_id` bigint(20) UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        }

        if (!Schema::hasTable('model_has_permissions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `model_has_permissions` (
              `permission_id` bigint(20) UNSIGNED NOT NULL,
              `model_type` varchar(255) NOT NULL,
              `model_id` bigint(20) UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        }

        if (!Schema::hasTable('role_has_permissions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `role_has_permissions` (
              `permission_id` bigint(20) UNSIGNED NOT NULL,
              `role_id` bigint(20) UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `role_has_permissions`
              ADD PRIMARY KEY (`permission_id`,`role_id`),
              ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);
SQL);
        }

        if (!Schema::hasTable('user_permissions')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `user_permissions` (
              `id` int(11) NOT NULL,
              `user_id` int(11) NOT NULL,
              `role` int(11) NOT NULL,
              `permissions` mediumtext DEFAULT NULL,
              `created_by` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_permissions`
              ADD PRIMARY KEY (`id`),
              ADD KEY `user_id` (`user_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `user_permissions`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('users_groups')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `users_groups` (
              `id` int(11) UNSIGNED NOT NULL,
              `user_id` int(11) UNSIGNED NOT NULL,
              `group_id` mediumint(8) UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `users_groups`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `uc_users_groups` (`user_id`,`group_id`),
              ADD KEY `fk_users_groups_users1_idx` (`user_id`),
              ADD KEY `fk_users_groups_groups1_idx` (`group_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `users_groups`
              MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('personal_access_tokens')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `personal_access_tokens` (
              `id` bigint(20) UNSIGNED NOT NULL,
              `tokenable_type` varchar(255) NOT NULL,
              `tokenable_id` bigint(20) UNSIGNED NOT NULL,
              `name` varchar(255) NOT NULL,
              `token` varchar(64) NOT NULL,
              `abilities` text DEFAULT NULL,
              `last_used_at` timestamp NULL DEFAULT NULL,
              `expires_at` timestamp NULL DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT NULL,
              `updated_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `personal_access_tokens`
              ADD PRIMARY KEY (`id`),
              ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
              ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `personal_access_tokens`
              MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('password_reset_tokens')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `password_reset_tokens` (
              `email` varchar(255) NOT NULL,
              `token` varchar(255) NOT NULL,
              `created_at` timestamp NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `password_reset_tokens`
              ADD PRIMARY KEY (`email`);
SQL);
        }

        if (!Schema::hasTable('login_attempts')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `login_attempts` (
              `id` int(11) UNSIGNED NOT NULL,
              `ip_address` varchar(45) NOT NULL,
              `login` varchar(100) NOT NULL,
              `time` int(11) UNSIGNED DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `login_attempts`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `login_attempts`
              MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('otps')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `otps` (
              `id` int(11) NOT NULL,
              `mobile` varchar(20) NOT NULL,
              `otp` varchar(20) NOT NULL,
              `varified` int(11) NOT NULL DEFAULT 0 COMMENT '1 : verify | 0: not verify',
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              `created_at` text NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `otps`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `otps`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }

        if (!Schema::hasTable('client_api_keys')) {
            DB::unprepared(<<<'SQL'
            CREATE TABLE `client_api_keys` (
              `id` int(11) NOT NULL,
              `name` mediumtext DEFAULT NULL,
              `secret` mediumtext NOT NULL,
              `status` int(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `client_api_keys`
              ADD PRIMARY KEY (`id`);
SQL);
            DB::unprepared(<<<'SQL'
            ALTER TABLE `client_api_keys`
              MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_api_keys');
        Schema::dropIfExists('otps');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('users_groups');
        Schema::dropIfExists('user_permissions');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
