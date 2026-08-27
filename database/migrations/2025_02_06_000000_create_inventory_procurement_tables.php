<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 (docs/PHASE_5_INVENTORY_PROCUREMENT.md): net-new schema, confirmed absent from the real dump
 * (docs/DATABASE_GAP_ANALYSIS.md §5). All FK-shaped columns are plain signed integer() (not foreignId()) for
 * the same reason as Phase 4's branches/employees migration - seller_data.id/users.id/products.id are legacy
 * int(11), and this codebase's convention is app-layer relationships, not DB-level FK constraints.
 *
 * `stock_items`/`stock_movements` deliberately reuse Phase 4's `branches` as the location concept rather
 * than adding a separate `warehouses` table - see PHASE_5_INVENTORY_PROCUREMENT.md §1 for why building both
 * would just be two near-identical location tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->string('name', 256);
                $table->string('contact_person', 256)->nullable();
                $table->string('phone', 32)->nullable();
                $table->string('email', 256)->nullable();
                $table->string('address', 512)->nullable();
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('supplier_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('status', 32)->default('draft')
                    ->comment('draft | ordered | partially_received | received | cancelled');
                $table->date('order_date')->nullable();
                $table->date('expected_date')->nullable();
                $table->text('notes')->nullable();
                $table->integer('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->integer('product_variant_id')->index();
                $table->integer('quantity');
                $table->decimal('unit_cost', 15, 4);
                $table->integer('received_quantity')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_received_notes')) {
            Schema::create('goods_received_notes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_order_id')->index();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->date('received_date');
                $table->integer('received_by')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_received_note_items')) {
            Schema::create('goods_received_note_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('goods_received_note_id')->index();
                $table->unsignedBigInteger('purchase_order_item_id')->index();
                $table->integer('product_variant_id')->index();
                $table->integer('quantity_received');
                $table->decimal('unit_cost', 15, 4);
                $table->timestamps();
            });
        }

        // Immutable ledger - every stock quantity change, whatever the source, is recorded here. Not
        // updated or deleted after creation (a correction is a new offsetting movement, same principle as
        // this codebase's financial-precision docs use for money).
        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->integer('product_variant_id')->index();
                $table->string('movement_type', 8)->comment('in | out - direction only; reference_type carries the reason');
                $table->integer('quantity')->comment('always positive; direction is movement_type');
                $table->decimal('unit_cost', 15, 4)->nullable();
                $table->string('reference_type', 64)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Running per-(seller, branch, variant) quantity - a materialized view of stock_movements kept in
        // sync at write time, so "what's on hand at this branch" doesn't need to re-sum the whole ledger on
        // every read. branch_id = null is the "unlocated" bucket every pre-Phase-4 seller's existing stock
        // falls into until they start using branches.
        if (!Schema::hasTable('stock_items')) {
            Schema::create('stock_items', function (Blueprint $table) {
                $table->id();
                $table->integer('seller_id')->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->integer('product_variant_id')->index();
                $table->integer('quantity')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('goods_received_note_items');
        Schema::dropIfExists('goods_received_notes');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
