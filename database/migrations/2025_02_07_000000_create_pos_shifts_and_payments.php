<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 (docs/PHASE_6_POS.md): shifts (till open/close with cash reconciliation) and per-order payment
 * lines (split payments - a POS order need not be paid with a single method). `orders.pos_shift_id` is
 * nullable and additive, same pattern as Phase 3's `orders.channel` - existing orders/code paths that never
 * set it keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_shifts')) {
            Schema::create('pos_shifts', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->integer('user_id')->index()->comment('the cashier who opened this shift');
                $table->decimal('opening_cash', 15, 4)->default(0);
                $table->decimal('closing_cash', 15, 4)->nullable()->comment('physically counted at close');
                $table->decimal('expected_cash', 15, 4)->nullable()->comment('opening + cash sales, computed at close');
                $table->decimal('cash_variance', 15, 4)->nullable()->comment('closing_cash - expected_cash');
                $table->string('status', 16)->default('open')->comment('open | closed');
                $table->text('notes')->nullable();
                $table->timestamp('opened_at')->useCurrent();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('pos_payments')) {
            Schema::create('pos_payments', function (Blueprint $table) {
                $table->id();
                $table->integer('order_id')->index();
                $table->unsignedBigInteger('pos_shift_id')->nullable()->index();
                $table->string('payment_method', 64);
                $table->decimal('amount', 15, 4);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('orders', 'pos_shift_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('pos_shift_id')->nullable()->after('is_pos_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'pos_shift_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('pos_shift_id');
            });
        }
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_shifts');
    }
};
