<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_DATABASE_MIGRATION_PLAN.md): proves the baseline migrations actually reproduce the
 * eShop Plus 1.0.6 schema, and that the InnoDB/DECIMAL conversions actually took effect - not just that
 * the migrations run without error.
 */
class MigrationBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_creates_the_expected_number_of_tables(): void
    {
        $tables = DB::select("SELECT COUNT(*) as c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name != 'migrations'")[0]->c;

        // 89 tables from the audited eShop Plus schema (docs/DATABASE_GAP_ANALYSIS.md §1), plus 4 tables
        // added after this test was written, for the Cloud Run production deploy (not part of the original
        // eShop Plus dump - see the migrations' own docblocks): `sessions`/`cache`/`cache_locks`/`jobs`,
        // needed because Cloud Run runs multiple stateless instances and this deployment sets
        // SESSION_DRIVER/CACHE_DRIVER/QUEUE_CONNECTION=database instead of the original file-based drivers.
        // Plus 2 more from Phase 4 (docs/PHASE_4_VENDOR_SYSTEM.md): `branches`/`employees`, confirmed absent
        // from the original dump (docs/DATABASE_GAP_ANALYSIS.md §5). Plus 7 more from Phase 5
        // (docs/PHASE_5_INVENTORY_PROCUREMENT.md): `suppliers`, `purchase_orders`, `purchase_order_items`,
        // `goods_received_notes`, `goods_received_note_items`, `stock_movements`, `stock_items` - all
        // confirmed absent from the original dump (docs/DATABASE_GAP_ANALYSIS.md §5). Plus 2 more from
        // Phase 6 (docs/PHASE_6_POS.md): `pos_shifts`, `pos_payments`. Plus 4 more from Phase 7
        // (docs/PHASE_7_AFFILIATE_ENGINE.md): `affiliate_links`, `link_clicks`, `commission_rules`,
        // `referral_conversions`. Plus 1 more from Phase 8 (docs/PHASE_8_DELIVERY.md): `delivery_earnings`.
        // Plus 3 more from Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md): `chart_of_accounts`,
        // `journal_entries`, `journal_lines`. Plus 5 more from Phase 10
        // (docs/PHASE_10_PARTNERS_ASSETS_LIABILITIES.md): `partners`, `partner_transactions`, `assets`,
        // `depreciation_schedules`, `liabilities`. Plus 4 more from Phase 11 (docs/PHASE_11_CRM.md):
        // `customer_notes`, `customer_tags`, `customer_tag_assignments`, `customer_segments`.
        $this->assertSame(121, (int) $tables);
    }

    /** @dataProvider myisamTables */
    public function test_previously_myisam_tables_are_now_innodb(string $table): void
    {
        $engine = DB::selectOne(
            "SELECT ENGINE as engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$table]
        )->engine;

        $this->assertSame('InnoDB', $engine, "{$table} should have been converted from MyISAM to InnoDB");
    }

    public static function myisamTables(): array
    {
        return [
            ['orders'], ['products'], ['wallet_transactions'], ['return_requests'], ['sections'],
            ['settings'], ['sliders'], ['time_slots'], ['notifications'], ['favorites'],
            ['delivery_boy_notifications'],
        ];
    }

    /** @dataProvider moneyColumns */
    public function test_monetary_columns_are_decimal_not_double(string $table, string $column, string $expectedType): void
    {
        $row = DB::selectOne(
            "SELECT DATA_TYPE as data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        );

        $this->assertNotNull($row, "{$table}.{$column} should exist");
        $this->assertSame($expectedType, strtolower($row->data_type));
    }

    public static function moneyColumns(): array
    {
        return [
            ['orders', 'total', 'decimal'],
            ['orders', 'order_payment_currency_conversion_rate', 'decimal'],
            ['wallet_transactions', 'amount', 'decimal'],
            ['users', 'balance', 'decimal'],
            ['currencies', 'exchange_rate', 'decimal'],
            ['order_items', 'admin_commission_amount', 'decimal'],
            ['seller_commissions', 'commission', 'decimal'],
        ];
    }

    public function test_seller_store_foreign_keys_survived_the_baseline(): void
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seller_store' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        );

        $this->assertCount(2, $constraints);
    }

    public function test_migrations_are_idempotent_on_a_database_that_already_has_the_schema(): void
    {
        // Simulates re-running the baseline against a database that already has the real production
        // schema (the guarded `if (!Schema::hasTable(...))` pattern - see
        // docs/PHASE_1_DATABASE_MIGRATION_PLAN.md "Migration bookkeeping").
        $this->assertTrue(Schema::hasTable('orders'));

        $this->artisan('migrate')->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('orders'));
    }
}
