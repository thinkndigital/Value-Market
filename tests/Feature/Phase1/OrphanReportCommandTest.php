<?php

namespace Tests\Feature\Phase1;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_DATA_INTEGRITY_REPORT.md, Task D): proves `db:orphan-report` actually detects an
 * orphaned row rather than trusting the command's own logic by inspection.
 */
class OrphanReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_zero_orphans_on_clean_data(): void
    {
        $user = User::forceCreate([
            'username' => 'clean_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
        ]);

        Order::forceCreate([
            'user_id' => $user->id,
            'mobile' => '1',
            'total' => 1,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);

        $this->artisan('db:orphan-report')
            ->expectsOutputToContain('No orphaned rows found')
            ->assertExitCode(0);
    }

    public function test_detects_an_orphaned_order(): void
    {
        DB::table('orders')->insert([
            'user_id' => 999999, // no such user
            'mobile' => '1',
            'total' => 1,
            'payment_method' => 'cod',
            'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD',
            'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('db:orphan-report')
            ->expectsOutputToContain('Found 1 orphaned row(s)')
            ->assertExitCode(0);
    }
}
