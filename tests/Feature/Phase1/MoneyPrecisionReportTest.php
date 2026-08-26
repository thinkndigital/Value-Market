<?php

namespace Tests\Feature\Phase1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_FINANCIAL_PRECISION.md, Task C): proves `money:precision-report` actually detects
 * non-numeric and precision-losing values rather than trusting the command's own logic by inspection.
 *
 * By the time RefreshDatabase runs the full migration set, currencies.exchange_rate is already DECIMAL, so
 * this test deliberately reverts it to the pre-Phase-1 varchar(256) column first to exercise the exact
 * scenario the report exists to catch before that migration runs on a real database.
 */
class MoneyPrecisionReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * DDL (ALTER TABLE) implicitly commits any open transaction in MySQL/MariaDB, which defeats
     * RefreshDatabase's normal "wrap each test in a transaction and roll it back" isolation - once a test
     * here runs its ALTER TABLE, there is no longer an open transaction to roll back, so both the schema
     * change and any inserted rows would otherwise leak into later tests (in this class and others sharing
     * the same DB connection/process). Explicitly reverting the column in tearDown() avoids relying on
     * RefreshDatabase for a scenario it cannot actually isolate.
     */
    protected function tearDown(): void
    {
        DB::statement("ALTER TABLE currencies MODIFY `exchange_rate` DECIMAL(20,10) DEFAULT NULL");
        DB::table('currencies')->truncate();

        parent::tearDown();
    }

    public function test_reports_clean_on_valid_numeric_data(): void
    {
        DB::statement("ALTER TABLE currencies MODIFY `exchange_rate` VARCHAR(256) DEFAULT NULL");
        DB::table('currencies')->insert([
            'name' => 'USD', 'code' => 'USD', 'symbol' => '$',
            'exchange_rate' => '1.0000', 'is_default' => 1, 'status' => 1,
        ]);

        $this->artisan('money:precision-report')
            ->expectsOutputToContain('No non-numeric or precision-loss values found')
            ->assertExitCode(0);
    }

    public function test_detects_non_numeric_exchange_rate(): void
    {
        DB::statement("ALTER TABLE currencies MODIFY `exchange_rate` VARCHAR(256) DEFAULT NULL");
        DB::table('currencies')->insert([
            'name' => 'Bad', 'code' => 'BAD', 'symbol' => 'B',
            'exchange_rate' => 'not-a-number', 'is_default' => 0, 'status' => 1,
        ]);

        $this->artisan('money:precision-report')
            ->expectsOutputToContain('currencies.exchange_rate')
            ->assertExitCode(1);
    }

    public function test_detects_precision_loss_beyond_target_scale(): void
    {
        DB::statement("ALTER TABLE currencies MODIFY `exchange_rate` VARCHAR(256) DEFAULT NULL");
        // target scale for exchange_rate is 10 - 11 fractional digits will lose the last one
        DB::table('currencies')->insert([
            'name' => 'Precise', 'code' => 'PRE', 'symbol' => 'P',
            'exchange_rate' => '1.23456789012', 'is_default' => 0, 'status' => 1,
        ]);

        $this->artisan('money:precision-report')
            ->expectsOutputToContain('currencies.exchange_rate')
            ->assertExitCode(1);
    }
}
