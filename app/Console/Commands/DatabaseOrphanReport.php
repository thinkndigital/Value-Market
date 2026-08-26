<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 - Foreign Key audit tooling (docs/PHASE_1_DATA_INTEGRITY_REPORT.md, Task D).
 *
 * Only 2 of ~89 real relationships in this schema are backed by a declared foreign key (both on
 * seller_store - see docs/DATABASE_GAP_ANALYSIS.md §2). Every other relationship (orders.user_id,
 * order_items.product_variant_id, products.seller_id, ...) is enforced only by application code, if at
 * all. Before adding a real FK constraint to any of them, existing orphaned rows must be found and
 * resolved (or the ADD CONSTRAINT will fail on production data) - that is what this command does.
 *
 * This does NOT delete or modify any data (per Phase 1 rule D: "Do not delete orphaned records
 * automatically"). It only reports. A human (or a follow-up, explicitly-scoped migration written after
 * reviewing this report's output) decides what happens to each flagged row.
 *
 * Usage: php artisan db:orphan-report [--csv=storage/app/orphan-report.csv]
 */
class DatabaseOrphanReport extends Command
{
    protected $signature = 'db:orphan-report {--csv= : Optional path to also write the report as CSV}';

    protected $description = 'Report rows whose foreign-key-shaped column points at a parent row that does not exist (Phase 1 Task D)';

    /**
     * [child_table, child_column, parent_table, parent_column]
     *
     * This list intentionally covers the relationships that matter most for Phase 1's financial/tenant
     * scope (orders, wallet, products, tenant ownership) rather than literally all ~88 implicit
     * relationships in the schema - see docs/PHASE_1_DATA_INTEGRITY_REPORT.md for the full inventory and
     * rationale for what's covered here vs. deferred to a later pass.
     */
    private array $relationships = [
        ['orders', 'user_id', 'users', 'id'],
        ['order_items', 'order_id', 'orders', 'id'],
        ['order_items', 'product_variant_id', 'product_variants', 'id'],
        ['order_items', 'seller_id', 'seller_data', 'id'],
        ['order_items', 'user_id', 'users', 'id'],
        ['order_charges', 'order_id', 'orders', 'id'],
        ['order_charges', 'seller_id', 'seller_data', 'id'],
        ['products', 'category_id', 'categories', 'id'],
        ['products', 'seller_id', 'seller_data', 'id'],
        ['product_variants', 'product_id', 'products', 'id'],
        ['cart', 'user_id', 'users', 'id'],
        ['cart', 'product_variant_id', 'product_variants', 'id'],
        ['favorites', 'user_id', 'users', 'id'],
        ['wallet_transactions', 'user_id', 'users', 'id'],
        ['transactions', 'user_id', 'users', 'id'],
        ['addresses', 'user_id', 'users', 'id'],
        ['seller_data', 'user_id', 'users', 'id'],
        ['payment_requests', 'user_id', 'users', 'id'],
        ['return_requests', 'user_id', 'users', 'id'],
        ['return_requests', 'order_id', 'orders', 'id'],
        ['return_requests', 'product_id', 'products', 'id'],
        ['parcels', 'order_id', 'orders', 'id'],
        ['parcel_items', 'parcel_id', 'parcels', 'id'],
        ['parcel_items', 'order_item_id', 'order_items', 'id'],
        ['product_attributes', 'product_id', 'products', 'id'],
        ['product_ratings', 'user_id', 'users', 'id'],
        ['product_ratings', 'product_id', 'products', 'id'],
        ['seller_commissions', 'seller_id', 'seller_data', 'id'],
        ['fund_transfers', 'delivery_boy_id', 'users', 'id'],
    ];

    public function handle(): int
    {
        $rows = [];
        $totalOrphans = 0;

        foreach ($this->relationships as [$childTable, $childColumn, $parentTable, $parentColumn]) {
            if (!Schema::hasTable($childTable) || !Schema::hasColumn($childTable, $childColumn)) {
                $this->warn("Skipping {$childTable}.{$childColumn} - table/column not found");
                continue;
            }
            if (!Schema::hasTable($parentTable)) {
                $this->warn("Skipping {$childTable}.{$childColumn} - parent table {$parentTable} not found");
                continue;
            }

            $orphanCount = DB::table($childTable . ' as c')
                ->leftJoin("{$parentTable} as p", "c.{$childColumn}", '=', "p.{$parentColumn}")
                ->whereNotNull("c.{$childColumn}")
                ->where("c.{$childColumn}", '!=', 0)
                ->whereNull("p.{$parentColumn}")
                ->count();

            $totalRows = DB::table($childTable)->count();

            $rows[] = [
                'child' => "{$childTable}.{$childColumn}",
                'parent' => "{$parentTable}.{$parentColumn}",
                'total_rows' => $totalRows,
                'orphans' => $orphanCount,
            ];

            $totalOrphans += $orphanCount;
        }

        $this->table(['Child column', 'References', 'Total rows', 'Orphan rows'], array_map(
            fn($r) => [$r['child'], $r['parent'], $r['total_rows'], $r['orphans'] > 0 ? "<fg=red>{$r['orphans']}</>" : $r['orphans']],
            $rows
        ));

        if ($totalOrphans > 0) {
            $this->newLine();
            $this->error("Found {$totalOrphans} orphaned row(s) across " . count(array_filter($rows, fn($r) => $r['orphans'] > 0)) . " relationship(s). Resolve these before adding a foreign key on the affected column(s). No data was changed.");
        } else {
            $this->newLine();
            $this->info('No orphaned rows found in the relationships checked. Safe to proceed with adding foreign keys for these columns (re-run this report against production data first if this was run against a non-production database).');
        }

        if ($csvPath = $this->option('csv')) {
            $fh = fopen(base_path($csvPath), 'w');
            fputcsv($fh, ['child_column', 'references', 'total_rows', 'orphan_rows']);
            foreach ($rows as $r) {
                fputcsv($fh, [$r['child'], $r['parent'], $r['total_rows'], $r['orphans']]);
            }
            fclose($fh);
            $this->info("CSV written to {$csvPath}");
        }

        return self::SUCCESS;
    }
}
