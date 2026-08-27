<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7 (docs/PHASE_7_AFFILIATE_ENGINE.md): net-new, confirmed absent from the real dump
 * (docs/DATABASE_GAP_ANALYSIS.md §5 - "Affiliate/Referral engine": only `users.referral_code`/`friends_code`
 * exist today, which power a DIFFERENT, already-working feature (the refer-a-friend wallet bonus in
 * app/function_helper.php's processReferralBonus() - a one-time signup bonus between two customers). This
 * is a distinct, larger feature: trackable links, click/conversion tracking, and a configurable commission
 * rule engine - left completely untouched, not replaced.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('affiliate_links')) {
            Schema::create('affiliate_links', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id')->index()->comment('the affiliate promoting this link');
                $table->string('target_type', 32)->comment('platform | store | category | product');
                $table->integer('target_id')->nullable();
                $table->string('code', 32)->unique();
                $table->unsignedInteger('clicks_count')->default(0);
                $table->unsignedInteger('conversions_count')->default(0);
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('link_clicks')) {
            Schema::create('link_clicks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('affiliate_link_id')->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('referrer', 512)->nullable();
                $table->timestamp('clicked_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 16)->comment('platform | vendor | affiliate | category | product');
                $table->integer('scope_id')->nullable()->comment('meaning depends on scope: seller_id | user_id | category_id | product_id; null for platform');
                $table->string('rate_type', 16)->comment('percentage | flat');
                $table->decimal('rate_value', 15, 4);
                $table->tinyInteger('status')->default(1)->comment('active: 1 | inactive: 0');
                $table->timestamps();
                $table->index(['scope', 'scope_id']);
            });
        }

        if (!Schema::hasTable('referral_conversions')) {
            Schema::create('referral_conversions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('affiliate_link_id')->index();
                $table->integer('order_id')->index();
                $table->integer('buyer_user_id')->nullable();
                $table->decimal('order_total', 15, 4);
                $table->string('commission_rate_type', 16);
                $table->decimal('commission_rate_value', 15, 4);
                $table->decimal('commission_amount', 15, 4);
                $table->string('status', 16)->default('pending')->comment('pending | approved | rejected');
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_conversions');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('link_clicks');
        Schema::dropIfExists('affiliate_links');
    }
};
