<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (Commerce Core, docs/PHASE_3_COMMERCE_CORE.md): `return_requests` had no way to capture why a
 * customer is returning an item, and no way to request less than the item's full ordered quantity - a
 * return was always "the whole line item or nothing." Both columns are nullable: existing/older app clients
 * that don't send them keep working unchanged, and the write path (OrderService::setUserReturnRequest())
 * defaults `quantity` to the order item's full ordered quantity when the caller doesn't provide one, so a
 * NULL here means "the whole item," not "unknown."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('return_requests', 'reason')) {
                $table->string('reason', 512)->nullable()->after('order_item_id');
            }
            if (!Schema::hasColumn('return_requests', 'quantity')) {
                $table->integer('quantity')->nullable()->after('reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $table) {
            if (Schema::hasColumn('return_requests', 'quantity')) {
                $table->dropColumn('quantity');
            }
            if (Schema::hasColumn('return_requests', 'reason')) {
                $table->dropColumn('reason');
            }
        });
    }
};
