<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 (docs/PHASE_10_PARTNERS_ASSETS_LIABILITIES.md): partner capital accounts, built on Phase 9's
 * ledger. Only contribution/withdrawal post to the general ledger - see recordTransaction()'s docblock for
 * why profit_share/loss_share deliberately don't.
 */
class PartnerService
{
    public function recordTransaction(int $partnerId, string $type, float $amount, ?string $description = null): PartnerTransaction
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive.');
        }
        if (!in_array($type, [
            PartnerTransaction::TYPE_CONTRIBUTION, PartnerTransaction::TYPE_WITHDRAWAL,
            PartnerTransaction::TYPE_PROFIT_SHARE, PartnerTransaction::TYPE_LOSS_SHARE,
        ], true)) {
            throw new \InvalidArgumentException('Invalid partner transaction type.');
        }

        return DB::transaction(function () use ($partnerId, $type, $amount, $description) {
            $journalEntryId = null;

            // contribution/withdrawal have an unambiguous, universal accounting treatment (cash moves
            // against the partner's equity) and post automatically. profit_share/loss_share allocate a
            // share of overall company profit/loss into ONE partner's capital account - correctly doing
            // that on the general ledger needs a per-partner equity sub-account (this pass's single
            // 3000 Owner's Equity account is shared across all partners, so crediting it per-partner would
            // conflate everyone's capital together). Recorded here for capital-balance tracking either way;
            // GL posting for profit/loss allocation is documented follow-up, not silently skipped.
            if ($type === PartnerTransaction::TYPE_CONTRIBUTION) {
                $entry = app(LedgerService::class)->postEntry(
                    "Partner contribution" . ($description ? ": {$description}" : ''),
                    [['account_code' => '1000', 'debit' => $amount], ['account_code' => '3000', 'credit' => $amount]],
                    'partner_transaction',
                    $partnerId
                );
                $journalEntryId = $entry->id;
            } elseif ($type === PartnerTransaction::TYPE_WITHDRAWAL) {
                $entry = app(LedgerService::class)->postEntry(
                    "Partner withdrawal" . ($description ? ": {$description}" : ''),
                    [['account_code' => '3000', 'debit' => $amount], ['account_code' => '1000', 'credit' => $amount]],
                    'partner_transaction',
                    $partnerId
                );
                $journalEntryId = $entry->id;
            }

            return PartnerTransaction::forceCreate([
                'partner_id' => $partnerId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'journal_entry_id' => $journalEntryId,
            ]);
        });
    }

    /**
     * Always computed by summing partner_transactions - never a separately-stored running total that could
     * drift out of sync.
     */
    public function capitalBalance(int $partnerId): float
    {
        $increasing = (float) PartnerTransaction::where('partner_id', $partnerId)
            ->whereIn('type', PartnerTransaction::INCREASING_TYPES)
            ->sum('amount');
        $decreasing = (float) PartnerTransaction::where('partner_id', $partnerId)
            ->whereNotIn('type', PartnerTransaction::INCREASING_TYPES)
            ->sum('amount');

        return $increasing - $decreasing;
    }
}
