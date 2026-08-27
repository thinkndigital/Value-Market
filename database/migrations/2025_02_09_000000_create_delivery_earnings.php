<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (docs/PHASE_8_DELIVERY.md): structured per-delivery driver earnings - confirmed absent
 * (docs/DATABASE_GAP_ANALYSIS.md §5 lists "structured driver earnings" as a gap; `fund_transfers`, already
 * built and wired to Admin\FundTransferController/Delivery_boy\CashCollectionController, is the OPPOSITE
 * direction of money - a driver handing COD cash they collected IN to the platform, not the platform paying
 * the driver a delivery fee. Untouched, not replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_earnings')) {
            Schema::create('delivery_earnings', function (Blueprint $table) {
                $table->id();
                $table->integer('delivery_boy_id')->index();
                $table->integer('order_id')->index();
                $table->integer('order_item_id')->unique();
                $table->decimal('amount', 15, 4);
                $table->string('rate_type', 16)->comment('flat | percentage');
                $table->decimal('rate_value', 15, 4);
                $table->timestamp('earned_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_earnings');
    }
};
