<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jobs table for the `database` queue connection.
 *
 * Not part of the original eShop Plus schema dump (docs/DATABASE_GAP_ANALYSIS.md) - the original app used
 * QUEUE_CONNECTION=sync. This deployment sets QUEUE_CONNECTION=database instead
 * (docs/CLOUD_RUN_DEPLOYMENT.md §7), which requires this table (`failed_jobs` already exists - see
 * 2025_01_01_000012_baseline_media_infra.php). Matches the stub Laravel's own `php artisan queue:table`
 * generates.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
