<?php

namespace Tests\Feature\Phase9;

use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md): the fundamental accounting invariant - total debits equal
 * total credits on every posted entry - is enforced by LedgerService::postEntry() itself, not left to
 * callers to get right.
 */
class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_balanced_entry_posts_successfully(): void
    {
        $entry = app(LedgerService::class)->postEntry('Test entry', [
            ['account_code' => '1000', 'debit' => 100],
            ['account_code' => '4000', 'credit' => 100],
        ]);

        $this->assertNotNull($entry->id);
        $this->assertSame(2, $entry->lines()->count());
    }

    public function test_an_unbalanced_entry_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(LedgerService::class)->postEntry('Unbalanced', [
            ['account_code' => '1000', 'debit' => 100],
            ['account_code' => '4000', 'credit' => 99],
        ]);
    }

    public function test_a_line_with_both_debit_and_credit_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(LedgerService::class)->postEntry('Bad line', [
            ['account_code' => '1000', 'debit' => 50, 'credit' => 50],
            ['account_code' => '4000', 'credit' => 50],
        ]);
    }

    public function test_a_single_line_entry_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(LedgerService::class)->postEntry('Too few lines', [
            ['account_code' => '1000', 'debit' => 50],
        ]);
    }

    public function test_an_unknown_account_code_is_rejected_and_nothing_is_written(): void
    {
        try {
            app(LedgerService::class)->postEntry('Bad account', [
                ['account_code' => '1000', 'debit' => 50],
                ['account_code' => '9999', 'credit' => 50],
            ]);
            $this->fail('Expected an exception for an unknown account code.');
        } catch (\InvalidArgumentException $e) {
            // expected
        }

        $this->assertSame(0, \App\Models\JournalEntry::count());
    }

    public function test_account_balance_is_debit_minus_credit_for_a_debit_normal_account(): void
    {
        app(LedgerService::class)->postEntry('Cash in', [
            ['account_code' => '1000', 'debit' => 300],
            ['account_code' => '4000', 'credit' => 300],
        ]);
        app(LedgerService::class)->postEntry('Cash out', [
            ['account_code' => '5000', 'debit' => 50],
            ['account_code' => '1000', 'credit' => 50],
        ]);

        $this->assertSame(250.0, app(LedgerService::class)->accountBalance('1000'));
    }

    public function test_account_balance_is_credit_minus_debit_for_a_credit_normal_account(): void
    {
        app(LedgerService::class)->postEntry('Revenue', [
            ['account_code' => '1000', 'debit' => 500],
            ['account_code' => '4000', 'credit' => 500],
        ]);

        $this->assertSame(500.0, app(LedgerService::class)->accountBalance('4000'));
    }

    public function test_a_three_way_split_entry_that_balances_is_accepted(): void
    {
        $entry = app(LedgerService::class)->postEntry('Split', [
            ['account_code' => '1000', 'debit' => 60],
            ['account_code' => '5000', 'debit' => 40],
            ['account_code' => '4000', 'credit' => 100],
        ]);

        $this->assertSame(3, $entry->lines()->count());
    }
}
