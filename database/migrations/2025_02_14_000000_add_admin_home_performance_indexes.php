<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance diagnosis of /admin/home (docs/PHASE_18_PERFORMANCE_ADMIN_HOME.md): every query on that page
 * (and the broader admin panel - these same columns are filtered by dozens of other Admin\* controllers,
 * confirmed via grep, not assumed) filters by one of these four columns, none of which carried an index -
 * confirmed via `SHOW INDEX`, not assumed. A new migration, not an edit to an existing one, per this
 * project's established "don't retroactively edit shipped migrations" discipline (see
 * 2025_02_13_000000_add_performance_indexes.php for the same pattern and reasoning).
 *
 * `order_items` gets a composite (store_id, created_at) rather than a single-column store_id index -
 * /admin/home's monthly/weekly/daily sales queries all filter store_id AND range/group on created_at in the
 * same query, so the composite serves that exact shape directly instead of requiring a second lookup step.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!$this->indexExists('order_items', 'order_items_store_id_created_at_index')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->index(['store_id', 'created_at']);
            });
        }
        if (!$this->indexExists('orders', 'orders_store_id_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('store_id');
            });
        }
        if (!$this->indexExists('combo_products', 'combo_products_store_id_index')) {
            Schema::table('combo_products', function (Blueprint $table) {
                $table->index('store_id');
            });
        }
        if (!$this->indexExists('users', 'users_role_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('role_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_store_id_created_at_index');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_store_id_index');
        });
        Schema::table('combo_products', function (Blueprint $table) {
            $table->dropIndex('combo_products_store_id_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::select('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?', [$indexName]);

        return count($result) > 0;
    }
};
