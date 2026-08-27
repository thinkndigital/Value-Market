<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 (Commerce Core, docs/PHASE_3_COMMERCE_CORE.md): `orders.is_pos_order` is the only real
 * order-origin signal today - a binary POS/not-POS flag, read only as a report filter, never a positive
 * "this is a marketplace order" marker. This adds a real named channel instead of one inferred from a flag's
 * absence.
 *
 * A new column, not a repurpose of `orders.type` (a dormant, fully unused eShop Plus schema-debt column -
 * reusing it risks a future reader assuming it was always meaningful) and not `order_items.order_type`
 * (already fully wired for an unrelated purpose: "regular_order" vs "combo_order" is a product-shape flag,
 * not a channel). `is_pos_order` itself is untouched - every existing `WHERE is_pos_order = 0` report/query
 * filter keeps working exactly as before; `channel` is additive.
 *
 * `affiliate` is defined as a valid value now (see App\Models\Order::CHANNEL_AFFILIATE) so Phase 7
 * (Affiliate/Reseller Engine, net-new per docs/IMPLEMENTATION_ROADMAP.md) doesn't need another migration
 * just to widen this column - no code path sets it yet, since no affiliate order-placement flow exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('channel', 32)->default('marketplace')->after('is_pos_order');
            });
        }

        DB::table('orders')->where('is_pos_order', 1)->update(['channel' => 'pos']);
        DB::table('orders')->where('is_pos_order', 0)->update(['channel' => 'marketplace']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'channel')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('channel');
            });
        }
    }
};
