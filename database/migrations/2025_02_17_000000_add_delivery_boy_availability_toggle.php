<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Changelog v1.0.11 ("Delivery Boy active/inactive availability toggle"): `users` already has THREE
 * distinct boolean-ish columns (`active`, `status`, `active_status`) with unclear/overlapping meaning
 * across this legacy schema - not confident enough, without a much deeper audit than this pass budgets for,
 * that any of them is safe to repurpose for a delivery boy's own online/offline self-toggle without
 * conflicting with what admin-side account activation or order-item status tracking already reads from it.
 * A new, single-purpose column avoids that risk. Nullable-with-default-1 so every existing delivery boy
 * starts "available" - no behavior change for anyone until they actually toggle it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'is_available')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_available')->default(1)->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_available')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_available');
            });
        }
    }
};
