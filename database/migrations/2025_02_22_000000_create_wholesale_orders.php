<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wholesaler module v2 (docs/WHOLESALER_MODULE.md): replaces the v1 "one-click import" flow (a seller
 * directly created their own Product from a wholesaler listing, no record of the transaction on the
 * wholesaler's side at all) with a real purchase-order workflow - a seller places an order for a quantity
 * at a chosen retail price, the wholesaler accepts/rejects/fulfills it, and only on fulfillment does the
 * seller's own Product get created/stocked (same underlying logic as v1's import, just triggered by
 * fulfillment instead of directly by the seller). This is what gives the wholesaler visibility into their
 * own orders/sales/clients - none of that existed when a seller could silently self-serve a Product row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('wholesale_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wholesaler_id');
            $table->unsignedBigInteger('wholesaler_product_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('store_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 4); // wholesale_price at order time, not a live reference
            $table->decimal('total_amount', 15, 4);
            $table->decimal('retail_price', 15, 4); // seller's chosen resale price, captured up front
            // 0 pending, 1 accepted, 2 shipped, 3 delivered (fulfillment: seller's Product row is created/
            // restocked at this point, not before), 4 rejected, 5 cancelled.
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('payment_status')->default(0); // 0 unpaid, 1 paid - manually marked, no gateway
            $table->text('seller_note')->nullable();
            $table->text('wholesaler_note')->nullable();
            // Set once fulfillment actually runs, so it can never run twice for the same order even if the
            // status transition handler is called again (e.g. a retried request).
            $table->unsignedBigInteger('fulfilled_product_id')->nullable();
            $table->timestamps();

            $table->index('wholesaler_id');
            $table->index('seller_id');
            $table->index('wholesaler_product_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_orders');
    }
};
