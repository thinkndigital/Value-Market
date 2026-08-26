<?php

namespace Tests\Feature\Phase1;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 (docs/PHASE_1_TRANSACTION_BOUNDARIES.md): verifies WalletService's balance-mutating methods
 * after wrapping them in DB::transaction() + lockForUpdate() - same business behavior as before
 * (debit/credit math, insufficient-balance rejection), now proven atomic rather than just asserted.
 */
class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(float $balance = 0): User
    {
        return User::forceCreate([
            'username' => 'wallet_test_' . uniqid(),
            'password' => 'x',
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'balance' => $balance,
        ]);
    }

    public function test_update_balance_add_increases_balance(): void
    {
        $user = $this->makeUser(100);

        app(WalletService::class)->updateBalance(50, $user->id, 'add');

        $this->assertEquals(150, $user->fresh()->balance);
    }

    public function test_update_balance_deduct_decreases_balance(): void
    {
        $user = $this->makeUser(100);

        app(WalletService::class)->updateBalance(30, $user->id, 'deduct');

        $this->assertEquals(70, $user->fresh()->balance);
    }

    public function test_update_wallet_balance_debit_rejects_amount_exceeding_balance(): void
    {
        $user = $this->makeUser(20);

        $response = app(WalletService::class)->updateWalletBalance('debit', $user->id, 50, 'test debit');

        $this->assertTrue($response['error']);
        $this->assertEquals(20, $user->fresh()->balance, 'balance must be unchanged when debit is rejected');
    }

    public function test_update_wallet_balance_credit_increases_balance_and_logs_transaction(): void
    {
        $user = $this->makeUser(10);

        $response = app(WalletService::class)->updateWalletBalance('credit', $user->id, 40, 'test credit');

        $this->assertFalse($response['error']);
        $this->assertEquals(50, $user->fresh()->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 40,
        ]);
    }

    public function test_update_wallet_balance_rejects_zero_amount(): void
    {
        $user = $this->makeUser(10);

        $response = app(WalletService::class)->updateWalletBalance('credit', $user->id, 0, 'zero test');

        $this->assertTrue($response['error']);
        $this->assertEquals(10, $user->fresh()->balance);
    }
}
