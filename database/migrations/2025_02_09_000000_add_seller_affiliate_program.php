<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Phase 7's affiliate engine (docs/PHASE_7_AFFILIATE_ENGINE.md) with a seller-facing side: until
 * now only admin could create a commission_rules row (Admin\CommissionRuleController), and an affiliate had
 * no way to discover eligible products except manually searching and generating a link one at a time. This
 * lets a seller opt individual products into the program (with their own commission rate - still just a
 * commission_rules row at scope=product, reusing that engine rather than duplicating it) and choose whether
 * their catalog is open to every affiliate or invite-only.
 *
 * `seller_store.affiliate_visibility`: 'public' (default - any affiliate can see and copy a ready link for
 * this store's commission-enabled products) or 'private' (an affiliate must first request access and be
 * approved). `store_affiliate_requests` records that request/approval - unique per (store, affiliate) so a
 * repeat request updates the same row instead of spamming new ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('seller_store', 'affiliate_visibility')) {
            Schema::table('seller_store', function (Blueprint $table) {
                $table->string('affiliate_visibility', 16)->default('public')->after('status');
            });
        }

        if (!Schema::hasTable('store_affiliate_requests')) {
            Schema::create('store_affiliate_requests', function (Blueprint $table) {
                $table->id();
                $table->integer('store_id')->index();
                $table->integer('user_id')->index()->comment('the affiliate requesting access');
                $table->string('status', 16)->default('pending')->comment('pending | approved | rejected');
                $table->timestamps();
                $table->unique(['store_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_affiliate_requests');

        if (Schema::hasColumn('seller_store', 'affiliate_visibility')) {
            Schema::table('seller_store', function (Blueprint $table) {
                $table->dropColumn('affiliate_visibility');
            });
        }
    }
};
