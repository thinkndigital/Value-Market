<?php

namespace App\Services;

use App\Models\Liability;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 (docs/PHASE_10_PARTNERS_ASSETS_LIABILITIES.md): liabilities, built on Phase 9's ledger. A loan
 * received has a clear, universal treatment (cash in, liability created) and posts automatically; any other
 * category's funding/purpose is caller-specific and doesn't (see recordOther()).
 */
class LiabilityService
{
    public function recordLoan(string $name, float $principal, ?string $dueDate = null): Liability
    {
        if ($principal <= 0) {
            throw new \InvalidArgumentException('Principal must be positive.');
        }

        return DB::transaction(function () use ($name, $principal, $dueDate) {
            $liability = Liability::forceCreate([
                'name' => $name,
                'category' => Liability::CATEGORY_LOAN,
                'principal_amount' => $principal,
                'outstanding_balance' => $principal,
                'due_date' => $dueDate,
                'status' => Liability::STATUS_ACTIVE,
            ]);

            app(LedgerService::class)->postEntry(
                "Loan received: {$name}",
                [['account_code' => '1000', 'debit' => $principal], ['account_code' => '2200', 'credit' => $principal]],
                'liability',
                $liability->id
            );

            return $liability;
        });
    }

    /**
     * A non-loan liability (accrued expense, other) - recorded for tracking, no automatic ledger posting
     * since the correct offsetting account depends on what the liability is actually for.
     */
    public function recordOther(string $name, string $category, float $principal, ?string $dueDate = null): Liability
    {
        if ($principal <= 0) {
            throw new \InvalidArgumentException('Principal must be positive.');
        }
        if (!in_array($category, [Liability::CATEGORY_ACCRUED_EXPENSE, Liability::CATEGORY_OTHER], true)) {
            throw new \InvalidArgumentException('Use recordLoan() for loans; this method is for accrued_expense/other.');
        }

        return Liability::forceCreate([
            'name' => $name,
            'category' => $category,
            'principal_amount' => $principal,
            'outstanding_balance' => $principal,
            'due_date' => $dueDate,
            'status' => Liability::STATUS_ACTIVE,
        ]);
    }

    public function recordPayment(Liability $liability, float $amount): Liability
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive.');
        }
        if ($amount > (float) $liability->outstanding_balance) {
            throw new \InvalidArgumentException('Payment cannot exceed the outstanding balance.');
        }

        return DB::transaction(function () use ($liability, $amount) {
            // Only 'loan' liabilities have a matching origination entry (recordLoan() posts one;
            // recordOther() deliberately doesn't - see its docblock). Posting a payment entry against a
            // liability whose origination was never posted would create a lone debit against Accounts
            // Payable with no offsetting credit ever recorded, driving that account to a nonsensical
            // negative balance - caught by writing a test for exactly this before trusting the method.
            if ($liability->category === Liability::CATEGORY_LOAN) {
                app(LedgerService::class)->postEntry(
                    "Liability payment: {$liability->name}",
                    [['account_code' => '2200', 'debit' => $amount], ['account_code' => '1000', 'credit' => $amount]],
                    'liability_payment',
                    $liability->id
                );
            }

            $liability->outstanding_balance = round((float) $liability->outstanding_balance - $amount, 4);
            if ($liability->outstanding_balance <= 0) {
                $liability->outstanding_balance = 0;
                $liability->status = Liability::STATUS_PAID;
            }
            $liability->save();

            return $liability;
        });
    }
}
