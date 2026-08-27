<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;

/**
 * Phase 9 (docs/PHASE_9_ACCOUNTING_LEDGER.md): the platform's double-entry ledger engine. postEntry() is the
 * only way a journal entry gets created - it enforces the fundamental accounting invariant (total debits =
 * total credits) before anything is written, and the whole entry (header + every line) commits atomically or
 * not at all.
 */
class LedgerService
{
    /**
     * @param array<int, array{account_code: string, debit?: float, credit?: float}> $lines Each line must
     *   have exactly one of debit/credit set to a positive amount - a line with both, or neither, is
     *   rejected rather than silently interpreted.
     */
    public function postEntry(string $description, array $lines, ?string $referenceType = null, ?int $referenceId = null, ?int $createdBy = null, ?string $entryDate = null): JournalEntry
    {
        if (count($lines) < 2) {
            throw new \InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $totalDebit = 0;
        $totalCredit = 0;
        $accountIdsByCode = [];

        foreach ($lines as $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (($debit > 0) === ($credit > 0)) {
                throw new \InvalidArgumentException('Each journal line must have exactly one of debit or credit set to a positive amount.');
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        // Float-safe equality - money is stored/compared at 4 decimal places throughout this codebase's
        // ledger-style tables (stock_movements, commission_rules, ...), so round before comparing rather
        // than risking a false mismatch from binary floating-point representation.
        if (round($totalDebit, 4) !== round($totalCredit, 4)) {
            throw new \InvalidArgumentException("Journal entry does not balance: debits {$totalDebit} != credits {$totalCredit}.");
        }

        return DB::transaction(function () use ($description, $lines, $referenceType, $referenceId, $createdBy, $entryDate) {
            $entry = JournalEntry::forceCreate([
                'entry_date' => $entryDate ?? now()->toDateString(),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $account = ChartOfAccount::where('code', $line['account_code'])->first();
                if (!$account) {
                    throw new \InvalidArgumentException("Unknown chart of accounts code: {$line['account_code']}");
                }

                JournalLine::forceCreate([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'created_at' => now(),
                ]);
            }

            return $entry;
        });
    }

    /**
     * Signed balance in the account's own normal-balance convention: positive means "more than zero" for
     * that account type (e.g. a positive balance on a liability account means the platform owes that much).
     */
    public function accountBalance(string $accountCode): float
    {
        $account = ChartOfAccount::where('code', $accountCode)->firstOrFail();

        $totals = JournalLine::where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) $totals->total_debit;
        $credit = (float) $totals->total_credit;

        return in_array($account->type, ChartOfAccount::DEBIT_NORMAL_TYPES, true) ? $debit - $credit : $credit - $debit;
    }
}
