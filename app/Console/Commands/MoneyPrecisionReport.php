<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 - Money Precision validation tooling (docs/PHASE_1_FINANCIAL_PRECISION.md, Task C).
 *
 * Run this BEFORE 2025_01_03_000000_convert_money_columns_to_decimal.php against any database that has
 * real data (this repo has none - see docs/PHASE_1_FINANCIAL_PRECISION.md "Data validation"). It does not
 * change any data. For each monetary/rate/exchange-rate column it reports:
 *  - non-numeric values (only possible today on the two columns that are currently varchar:
 *    currencies.exchange_rate, combo_products.delivery_charges) - these would make the conversion
 *    migration's ALTER TABLE fail outright, not just lose precision.
 *  - values whose decimal precision exceeds the column's target scale (4 places for amount/exchange-rate
 *    columns, 4 for rate columns) - these would be silently rounded by the ALTER TABLE MODIFY, which Task C
 *    explicitly says not to do without surfacing it first.
 *
 * Usage: php artisan money:precision-report [--csv=storage/app/money-precision-report.csv]
 */
class MoneyPrecisionReport extends Command
{
    protected $signature = 'money:precision-report {--csv= : Optional path to also write the report as CSV}';

    protected $description = 'Report monetary column values that are non-numeric or would lose precision under the Phase 1 DECIMAL conversion (Task C)';

    /** [table, column, scale] - same classification as the conversion migration. */
    private array $columns = [
        ['areas', 'minimum_free_delivery_order_amount', 4], ['areas', 'delivery_charges', 4],
        ['cities', 'minimum_free_delivery_order_amount', 4], ['cities', 'delivery_charges', 4],
        ['combo_products', 'price', 4], ['combo_products', 'special_price', 4], ['combo_products', 'delivery_charges', 4],
        ['fund_transfers', 'opening_balance', 4], ['fund_transfers', 'closing_balance', 4], ['fund_transfers', 'amount', 4],
        ['orders', 'total', 4], ['orders', 'delivery_charge', 4], ['orders', 'wallet_balance', 4],
        ['orders', 'promo_discount', 4], ['orders', 'discount', 4], ['orders', 'total_payable', 4], ['orders', 'final_total', 4],
        ['order_charges', 'delivery_charge', 4], ['order_charges', 'promo_discount', 4], ['order_charges', 'sub_total', 4], ['order_charges', 'total', 4],
        ['order_items', 'price', 4], ['order_items', 'discounted_price', 4], ['order_items', 'tax_amount', 4],
        ['order_items', 'discount', 4], ['order_items', 'sub_total', 4], ['order_items', 'admin_commission_amount', 4], ['order_items', 'seller_commission_amount', 4],
        ['parcels', 'delivery_charge', 4], ['parcel_items', 'unit_price', 4],
        ['products', 'delivery_charges', 4], ['product_variants', 'price', 4], ['product_variants', 'special_price', 4],
        ['promo_codes', 'minimum_order_amount', 4], ['promo_codes', 'discount', 4], ['promo_codes', 'max_discount_amount', 4],
        ['seller_commissions', 'commission', 4], ['seller_store', 'commission', 4],
        ['transactions', 'amount', 4], ['users', 'balance', 4], ['users', 'cash_received', 4],
        ['wallet_transactions', 'amount', 4], ['zipcodes', 'minimum_free_delivery_order_amount', 4], ['zipcodes', 'delivery_charges', 4],
        ['order_items', 'tax_percent', 4],
        ['currencies', 'exchange_rate', 10], ['orders', 'order_payment_currency_conversion_rate', 10],
    ];

    public function handle(): int
    {
        $rows = [];
        $totalFlagged = 0;

        foreach ($this->columns as [$table, $column, $scale]) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $type = DB::selectOne(
                "SELECT DATA_TYPE as data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            )->data_type ?? null;

            if (in_array(strtolower((string) $type), ['decimal', 'numeric'], true)) {
                continue; // already converted - nothing to validate
            }

            $totalRows = DB::table($table)->whereNotNull($column)->count();
            if ($totalRows === 0) {
                continue;
            }

            // Non-numeric values (only relevant for the varchar-typed columns).
            $nonNumeric = DB::table($table)
                ->whereNotNull($column)
                ->where($column, 'NOT REGEXP', '^-?[0-9]+(\\.[0-9]+)?$')
                ->count();

            // Values whose fractional part has more digits than the target scale - i.e. would be rounded.
            // Cast to a deliberately higher-precision intermediate (DECIMAL(30,15)) BEFORE comparing to the
            // target scale - casting straight to DECIMAL(30,{scale}) would round at cast time and always
            // compare equal to itself, hiding the exact precision loss this check exists to catch.
            $precisionLoss = DB::table($table)
                ->whereNotNull($column)
                ->where($column, 'REGEXP', '^-?[0-9]+(\\.[0-9]+)?$') // numeric only, avoid double-flagging non-numerics
                ->whereRaw("ABS(CAST(`{$column}` AS DECIMAL(30,15)) - ROUND(CAST(`{$column}` AS DECIMAL(30,15)), ?)) > 0.000000000001", [$scale])
                ->count();

            if ($nonNumeric > 0 || $precisionLoss > 0) {
                $rows[] = [
                    'column' => "{$table}.{$column}",
                    'type' => $type,
                    'target_scale' => $scale,
                    'rows_checked' => $totalRows,
                    'non_numeric' => $nonNumeric,
                    'precision_loss' => $precisionLoss,
                ];
                $totalFlagged += $nonNumeric + $precisionLoss;
            }
        }

        if (empty($rows)) {
            $this->info('No non-numeric or precision-loss values found in the monetary columns checked. Safe to run the DECIMAL conversion migration (re-run this report against production data first if this was run against a non-production database).');
            return self::SUCCESS;
        }

        $this->table(
            ['Column', 'Current type', 'Target scale', 'Rows checked', 'Non-numeric', 'Would lose precision'],
            array_map(fn($r) => [
                $r['column'], $r['type'], $r['target_scale'], $r['rows_checked'],
                $r['non_numeric'] > 0 ? "<fg=red>{$r['non_numeric']}</>" : $r['non_numeric'],
                $r['precision_loss'] > 0 ? "<fg=yellow>{$r['precision_loss']}</>" : $r['precision_loss'],
            ], $rows)
        );

        $this->newLine();
        $this->error("Flagged {$totalFlagged} value(s) across " . count($rows) . " column(s). Non-numeric values will make the conversion migration fail outright; precision-loss values will be silently rounded if you proceed without reviewing them. No data was changed.");

        if ($csvPath = $this->option('csv')) {
            $fh = fopen(base_path($csvPath), 'w');
            fputcsv($fh, ['column', 'current_type', 'target_scale', 'rows_checked', 'non_numeric', 'precision_loss']);
            foreach ($rows as $r) {
                fputcsv($fh, [$r['column'], $r['type'], $r['target_scale'], $r['rows_checked'], $r['non_numeric'], $r['precision_loss']]);
            }
            fclose($fh);
            $this->info("CSV written to {$csvPath}");
        }

        return self::FAILURE;
    }
}
