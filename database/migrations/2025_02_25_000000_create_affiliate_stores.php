<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master architecture prompt Phase 7 (Affiliate architecture, section 26 "Affiliate Store" - a
 * mini-store/landing page the affiliate can publish, listing a curated set of their own tracked products,
 * per the section 80 final acceptance criteria: "Affiliate can create a mini-store/landing page").
 *
 * `affiliate_store_products` deliberately references `affiliate_link_id`, not `product_id` directly - the
 * store can only ever feature a product the affiliate has already generated a link for (their "My
 * Products" list, see AffiliateController::myProductsList()), so every click on the public store page
 * reuses that same already-tracked link/redirect - no separate tracking mechanism for the store page.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('affiliate_stores')) {
            Schema::create('affiliate_stores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('slug')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('logo')->nullable();
                $table->string('banner')->nullable();
                $table->tinyInteger('status')->default(0); // 0 draft, 1 published
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('affiliate_store_products')) {
            Schema::create('affiliate_store_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('affiliate_store_id');
                $table->unsignedBigInteger('affiliate_link_id');
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['affiliate_store_id', 'affiliate_link_id'], 'aff_store_link_unique');
                $table->index('affiliate_store_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_store_products');
        Schema::dropIfExists('affiliate_stores');
    }
};
