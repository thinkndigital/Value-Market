<?php

namespace Tests\Feature\Phase1;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_TRANSACTION_BOUNDARIES.md): proves the core claim of Tasks B + E together - that
 * DB::transaction() now actually rolls back on the tables that matter, because they are InnoDB. Before
 * Phase 1, `orders` was MyISAM (no transaction support at all) and nothing in the codebase called
 * DB::transaction() around order writes, so this exact scenario would have left the row committed.
 */
class TransactionAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_transaction_on_orders_rolls_back_completely(): void
    {
        $user = User::forceCreate([
            'username' => 'txn_test_user',
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
        ]);

        $this->assertSame(0, Order::count());

        try {
            DB::transaction(function () use ($user) {
                Order::forceCreate([
                    'user_id' => $user->id,
                    'mobile' => '9999999999',
                    'total' => 100,
                    'payment_method' => 'cod',
                    'order_payment_currency_id' => 1,
                    'order_payment_currency_code' => 'USD',
                    'base_currency_code' => 'USD',
                    'order_payment_currency_conversion_rate' => 1,
                ]);

                throw new \RuntimeException('forced failure mid-transaction');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(
            0,
            Order::count(),
            'Order row should not exist after a rolled-back transaction - this only holds because orders is now InnoDB.'
        );
    }

    public function test_a_successful_transaction_on_orders_commits(): void
    {
        $user = User::forceCreate([
            'username' => 'txn_test_user_2',
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
        ]);

        DB::transaction(function () use ($user) {
            Order::forceCreate([
                'user_id' => $user->id,
                'mobile' => '9999999999',
                'total' => 100,
                'payment_method' => 'cod',
                'order_payment_currency_id' => 1,
                'order_payment_currency_code' => 'USD',
                'base_currency_code' => 'USD',
                'order_payment_currency_conversion_rate' => 1,
            ]);
        });

        $this->assertSame(1, Order::count());
    }
}
