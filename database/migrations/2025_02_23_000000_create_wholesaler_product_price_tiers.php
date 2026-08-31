<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 18 "Wholesale" group: Wholesale
 * Pricing / Bulk Pricing / Seller Pricing / Seller-Specific Pricing / Quantity Discounts) - one table
 * covers all of those: a tier with `seller_id = null` is a generic quantity-break price open to every
 * seller; a tier with `seller_id` set is a negotiated price for that one seller only, which always wins
 * over a generic tier at the same quantity (see WholesalerProduct::priceFor()). MOQ itself already
 * existed (`wholesaler_products.min_order_qty`, enforced since v2's order-placement validation) - this
 * only adds *how much per unit* once that minimum is met.
 */
return new class extends Migration {
    public function up(): void
    {
        // Guarded per this app's own idempotent-migration convention (docs/PHASE_1_DATABASE_MIGRATION_PLAN.md
        // "Migration bookkeeping") so a re-run against a database that already has the table is a safe no-op.
        if (!Schema::hasTable('wholesaler_product_price_tiers')) {
            Schema::create('wholesaler_product_price_tiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wholesaler_product_id');
                $table->unsignedBigInteger('seller_id')->nullable(); // null = applies to every seller
                $table->integer('min_quantity');
                $table->decimal('unit_price', 15, 4);
                $table->timestamps();

                $table->index('wholesaler_product_id');
                // Explicit short name - the auto-generated one (table + both column names + "_index")
                // exceeds MySQL's 64-char identifier limit.
                $table->index(['wholesaler_product_id', 'seller_id'], 'wptiers_product_seller_idx');
            });
        } elseif (!Schema::hasIndex('wholesaler_product_price_tiers', 'wptiers_product_seller_idx')) {
            Schema::table('wholesaler_product_price_tiers', function (Blueprint $table) {
                $table->index(['wholesaler_product_id', 'seller_id'], 'wptiers_product_seller_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesaler_product_price_tiers');
    }
};
