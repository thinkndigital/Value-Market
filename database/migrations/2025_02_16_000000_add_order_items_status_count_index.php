<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 (docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md): EXPLAIN-verified before adding. /admin/home's
 * (now-deduplicated - see HomeController::index()'s $orders_status_counts) 7 calls to
 * OrderService::ordersCount() run `SELECT COUNT(DISTINCT order_id) ... WHERE store_id = ? AND active_status
 * (= or !=) ? AND EXISTS(...)`. Against a seeded 145k-row, 3-store order_items table (~17% selectivity for
 * the profiled store - see the Phase 18 migration's own covering-index sibling in this phase for why a
 * single-store seed hides this), every one of these ran `type: ALL` (a 143,796-row full scan) - neither the
 * existing `active_status`-prefix index nor the Phase 19 (store_id, created_at, ...) financial covering
 * index added above serve this WHERE shape (store_id + active_status together, not date-ranged).
 *
 * Verified directly: creating a candidate (store_id, active_status(50), order_id) index and re-running
 * EXPLAIN flipped both query shapes from `type: ALL` (143,796 rows) to `type: ref` - ~12,682 rows for a
 * specific status, ~52,774 for the "all statuses" total (still large because "not awaiting" matches most
 * rows, but real narrowing versus scanning the entire table regardless of store). A prefix length matching
 * the existing `order_items_active_status_prefix_index`'s convention (50) - `active_status` is a
 * `varchar(1024)`, and a full-column composite index here exceeds InnoDB's 3072-byte key length limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('order_items', 'order_items_store_id_active_status_order_id_index')) {
            DB::statement('ALTER TABLE order_items ADD INDEX order_items_store_id_active_status_order_id_index (store_id, active_status(50), order_id)');
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_store_id_active_status_order_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);

        return count($result) > 0;
    }
};
