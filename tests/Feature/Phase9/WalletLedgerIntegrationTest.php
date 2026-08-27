<?php

namespace Tests\Feature\Phase9;

use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md §3): WalletService::updateWalletBalance() is the one method
 * all 15 existing wallet-changing call sites funnel through - extended to post a balanced journal entry for
 * every real balance change, without touching any of those 15 sites (same chokepoint pattern Phase 5 used
 * for ProductService::updateStock()).
 */
class WalletLedgerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(float $balance = 0): User
    {
        return User::forceCreate([
            'username' => 'user_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::CUSTOMER, 'balance' => $balance,
        ]);
    }

    public function test_a_credit_posts_a_balanced_entry_increasing_the_wallet_liability_account(): void
    {
        $user = $this->makeUser();
        $before = app(LedgerService::class)->accountBalance('2100');

        app(WalletService::class)->updateWalletBalance('credit', $user->id, 25, 'Test credit');

        $this->assertSame($before + 25, app(LedgerService::class)->accountBalance('2100'));
        $this->assertSame(1, JournalEntry::where('reference_type', 'wallet_transaction')->where('reference_id', $user->id)->count());
    }

    public function test_a_debit_posts_a_balanced_entry_decreasing_the_wallet_liability_account(): void
    {
        $user = $this->makeUser(balance: 100);
        $before = app(LedgerService::class)->accountBalance('2100');

        app(WalletService::class)->updateWalletBalance('debit', $user->id, 30, 'Test debit');

        $this->assertSame($before - 30, app(LedgerService::class)->accountBalance('2100'));
    }

    public function test_a_razorpay_credit_that_does_not_move_balance_posts_no_journal_entry(): void
    {
        $user = $this->makeUser();
        Transaction::forceCreate([
            'transaction_type' => 'transaction', 'user_id' => $user->id, 'order_item_id' => 'order-777',
            'type' => 'razorpay', 'amount' => 50, 'status' => 'success', 'message' => 'seed',
        ]);
        $entriesBefore = JournalEntry::count();

        app(WalletService::class)->updateWalletBalance('credit', $user->id, 50, 'Razorpay credit', 'order-777');

        $this->assertSame(0.0, (float) $user->fresh()->balance, 'balance should not have moved for the razorpay case');
        $this->assertSame($entriesBefore, JournalEntry::count(), 'no ledger entry should be posted when the balance did not actually change');
    }

    public function test_every_wallet_journal_entry_this_test_writes_stays_balanced(): void
    {
        $user = $this->makeUser(balance: 200);

        app(WalletService::class)->updateWalletBalance('credit', $user->id, 40, 'Credit 1');
        app(WalletService::class)->updateWalletBalance('debit', $user->id, 15, 'Debit 1');
        app(WalletService::class)->updateWalletBalance('credit', $user->id, 5, 'Credit 2');

        foreach (JournalEntry::where('reference_type', 'wallet_transaction')->get() as $entry) {
            $totalDebit = (float) $entry->lines()->sum('debit');
            $totalCredit = (float) $entry->lines()->sum('credit');
            $this->assertSame($totalDebit, $totalCredit, "journal entry {$entry->id} is not balanced");
        }
    }
}
