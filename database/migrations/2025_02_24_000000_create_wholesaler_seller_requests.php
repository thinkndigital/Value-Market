<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master architecture prompt Phase 6 (Supplier architecture, section 18 "Sellers" group: Explore Sellers /
 * Seller Requests / Approved Sellers / Pending Sellers). Mirrors the existing seller-managed affiliate
 * program's private-store request flow (`seller_store.affiliate_visibility` +
 * `store_affiliate_requests`, 2025_02_09_000000_add_seller_affiliate_program.php) exactly, one level up:
 * a wholesaler can gate its whole marketplace listing behind approval instead of every seller being able
 * to order from any approved wholesaler with no relationship at all.
 *
 * `wholesalers.buyer_visibility` defaults to 'public', so every wholesaler already in production (and
 * every existing test) keeps today's open-marketplace behavior unless a wholesaler explicitly switches to
 * 'private' - this is additive, not a behavior change.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('wholesalers', 'buyer_visibility')) {
            Schema::table('wholesalers', function (Blueprint $table) {
                $table->string('buyer_visibility', 16)->default('public')->after('status');
            });
        }

        if (!Schema::hasTable('wholesaler_seller_requests')) {
            Schema::create('wholesaler_seller_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('wholesaler_id');
                $table->unsignedBigInteger('seller_id');
                $table->string('status', 16)->default('pending'); // pending / approved / rejected
                $table->timestamps();

                $table->unique(['wholesaler_id', 'seller_id']);
                $table->index('wholesaler_id');
                $table->index('seller_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesaler_seller_requests');
        if (Schema::hasColumn('wholesalers', 'buyer_visibility')) {
            Schema::table('wholesalers', function (Blueprint $table) {
                $table->dropColumn('buyer_visibility');
            });
        }
    }
};
