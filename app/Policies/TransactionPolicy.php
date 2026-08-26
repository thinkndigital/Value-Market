<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * Phase 2 (docs/PHASE_2_RBAC_ARCHITECTURE.md, Task 5): a wallet/order Transaction record is private to the
 * user it belongs to (`transactions.user_id`) - this is the "Wallet/WalletTransaction" resource named in
 * the Phase 2 master prompt; there is no separate Wallet model in this codebase, wallet balance lives on
 * `users.balance` (see App\Services\WalletService) and its ledger is this Transaction table.
 */
class TransactionPolicy
{
    public function view(User $user, Transaction $transaction): bool
    {
        return (int) $transaction->user_id === (int) $user->id;
    }
}
