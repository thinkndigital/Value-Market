<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 16 (docs/IMPLEMENTATION_ROADMAP.md, Performance Optimization): indexes for query patterns confirmed
 * real by two independent checks - direct inspection of the live schema (`SHOW INDEX`, confirming these
 * columns carry no index today) and this session's own Phase 4-15 code, which filters by every column
 * indexed here in multiple places (docs/PHASE_16_PERFORMANCE_OPTIMIZATION.md has the full list). Not a
 * speculative "index everything" pass - only columns actually confirmed both unindexed and actually queried.
 *
 * Purely additive: adding a secondary index never changes query results, only read speed (at the cost of a
 * small amount of write overhead and disk space, the standard, well-understood trade-off). Safe to run at
 * any table size - MariaDB's default InnoDB online DDL (ALGORITHM=INPLACE) permits concurrent reads/writes
 * while a secondary index is being built.
 *
 * `order_items.active_status`/`status` are `varchar(1024)` (legacy schema) - full-column indexes on them
 * would exceed InnoDB's key-length limit under utf8mb4 (1024 x 4 bytes = 4096 > 3072-byte max even with
 * innodb_large_prefix). Every value actually stored is a short status word ("delivered", "cancelled",
 * "return_request_approved", ...), so a 50-character prefix index is used instead - long enough that no
 * real value collides within the prefix, short enough to stay well under the limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        // order_items: filtered by seller_id in essentially every Seller-panel query in this application
        // (confirmed: this session alone wrote dozens of `OrderItems::where('seller_id', ...)` call sites
        // across Phases 2-15), and by active_status in every delivered/cancelled/returned status filter
        // (AnalyticsService::salesSummary()/topSellingProducts() - Phase 12 - are the clearest new examples,
        // but the pattern predates this session). Confirmed via `SHOW INDEX FROM order_items`: only the
        // primary key and single-column indexes on user_id/order_id/product_variant_id existed before this
        // migration - seller_id and active_status had none.
        if (!$this->indexExists('order_items', 'order_items_seller_id_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index('seller_id');
            });
        }
        if (!$this->indexExists('order_items', 'order_items_active_status_prefix_index')) {
            DB::statement('ALTER TABLE order_items ADD INDEX order_items_active_status_prefix_index (active_status(50))');
        }
        // The combined (seller_id, active_status) shape is the exact filter AnalyticsService::salesSummary()
        // and topSellingProducts() use, and the shape most Seller-panel order-status list queries use too.
        if (!$this->indexExists('order_items', 'order_items_seller_id_active_status_prefix_index')) {
            DB::statement('ALTER TABLE order_items ADD INDEX order_items_seller_id_active_status_prefix_index (seller_id, active_status(50))');
        }

        // products: filtered by seller_id in every seller product listing/ownership check across the
        // application (this session's own Phase 5 PurchaseOrderController variant-ownership check, Phase 11
        // CRM, and the pre-existing Seller product panel all do this). Confirmed unindexed via `SHOW INDEX
        // FROM products` (only primary key + category_id existed).
        if (!$this->indexExists('products', 'products_seller_id_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('seller_id');
            });
        }

        // orders.channel: added by this session's own Phase 3 (docs/PHASE_3_COMMERCE_CORE.md) specifically
        // as a report-filter dimension, but the migration that added it didn't index it - an oversight
        // caught here rather than carried forward.
        if (!$this->indexExists('orders', 'orders_channel_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('channel');
            });
        }

        // referral_conversions: every write/read this session's Phase 7/15 AffiliateService code does
        // (recordConversion, approveConversionsForOrder, reverseConversionsForOrder) filters by exactly
        // this (order_id, status) pair. order_id alone was already indexed (Phase 7's own migration);
        // this adds the composite the actual query shape uses.
        if (!$this->indexExists('referral_conversions', 'referral_conversions_order_id_status_index')) {
            Schema::table('referral_conversions', function (Blueprint $table) {
                $table->index(['order_id', 'status']);
            });
        }

        // pos_payments: PosShiftService::close() sums cash payments filtered by exactly this
        // (pos_shift_id, payment_method) pair on every shift close. pos_shift_id alone was already indexed
        // (Phase 6's own migration); this adds the composite the actual query shape uses.
        if (!$this->indexExists('pos_payments', 'pos_payments_pos_shift_id_payment_method_index')) {
            Schema::table('pos_payments', function (Blueprint $table) {
                $table->index(['pos_shift_id', 'payment_method']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_seller_id_index');
            $table->dropIndex('order_items_active_status_prefix_index');
            $table->dropIndex('order_items_seller_id_active_status_prefix_index');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_seller_id_index');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_channel_index');
        });
        Schema::table('referral_conversions', function (Blueprint $table) {
            $table->dropIndex('referral_conversions_order_id_status_index');
        });
        Schema::table('pos_payments', function (Blueprint $table) {
            $table->dropIndex('pos_payments_pos_shift_id_payment_method_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);

        return count($result) > 0;
    }
};
