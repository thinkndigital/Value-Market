<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sessions table for the `database` session driver.
 *
 * Not part of the original eShop Plus schema dump (docs/DATABASE_GAP_ANALYSIS.md) - the original app used
 * SESSION_DRIVER=file. Cloud Run deployments run multiple stateless instances, so this deployment sets
 * SESSION_DRIVER=database instead (docs/CLOUD_RUN_DEPLOYMENT.md §7), which requires this table. Matches the
 * stub Laravel's own `php artisan session:table` generates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
