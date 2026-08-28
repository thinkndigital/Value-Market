<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 19 (docs/PHASE_19_ADMIN_HOME_QUERY_PROFILING.md): real EXPLAIN evidence, not a guess. Against a
 * seeded 145k-row order_items table where the profiled store held ~17% of the rows (a single-store seed
 * makes store_id match 100% of the table and hides this entirely - see that doc for why), /admin/home's
 * three whole-store financial aggregates - AdmintotalEarnings() (SUM(sub_total)), getMonthlyDataCombined()
 * and getWeeklySalesData() (SUM(sub_total)/SUM(admin_commission_amount)/SUM(quantity) grouped by month/day)
 * - all still ran as `type: ALL` full table scans under the (store_id, created_at) index added in Phase 18
 * (2025_02_14_000000): MySQL correctly judged that a non-covering secondary index still needs a bookmark
 * lookup into the clustered index for every matching row just to read sub_total/admin_commission_amount/
 * quantity, and at this selectivity a full scan was cheaper than that. Verified by adding a candidate index
 * directly via SQL and re-running EXPLAIN before writing this migration: all three queries flipped to
 * `Using index` (no bookmark lookups) once sub_total/admin_commission_amount/quantity were added to the
 * index itself.
 *
 * The new index is a strict left-prefix superset of Phase 18's (store_id, created_at) index (same leading
 * two columns, plus the three summed columns appended), so that older index is now fully redundant - kept
 * would mean extra write-time cost (InnoDB maintains every secondary index on every insert/update) for zero
 * read benefit, since any query plan that used it can use this one identically. Dropped in this same
 * migration rather than left in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('order_items', 'order_items_store_id_created_at_financials_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['store_id', 'created_at', 'sub_total', 'admin_commission_amount', 'quantity'], 'order_items_store_id_created_at_financials_index');
            });
        }
        if ($this->indexExists('order_items', 'order_items_store_id_created_at_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex('order_items_store_id_created_at_index');
            });
        }
    }

    public function down(): void
    {
        if (!$this->indexExists('order_items', 'order_items_store_id_created_at_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['store_id', 'created_at'], 'order_items_store_id_created_at_index');
            });
        }
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_store_id_created_at_financials_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);

        return count($result) > 0;
    }
};
