<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache tables for the `database` cache driver.
 *
 * Not part of the original eShop Plus schema dump (docs/DATABASE_GAP_ANALYSIS.md) - the original app used
 * CACHE_DRIVER=file. Cloud Run deployments run multiple stateless instances, so this deployment sets
 * CACHE_DRIVER=database instead (docs/CLOUD_RUN_DEPLOYMENT.md §7), which requires these tables. Matches the
 * stub Laravel's own `php artisan cache:table` generates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
