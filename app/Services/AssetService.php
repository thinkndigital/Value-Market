<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\DepreciationSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 (docs/PHASE_10_PARTNERS_ASSETS_LIABILITIES.md): fixed assets and straight-line depreciation,
 * built on Phase 9's ledger. registerAsset() deliberately does NOT post an acquisition journal entry - how
 * an asset was paid for (cash, a loan, a mix) is caller-specific context this method doesn't have; use
 * LedgerService::postEntry() or LiabilityService::recordLoan() directly for that. Depreciation, in
 * contrast, is a well-defined, universal accounting treatment and posts automatically.
 */
class AssetService
{
    public function registerAsset(string $name, ?string $category, float $cost, string $acquisitionDate, ?int $usefulLifeMonths = null, float $salvageValue = 0): Asset
    {
        if ($cost <= 0) {
            throw new \InvalidArgumentException('Acquisition cost must be positive.');
        }
        if ($salvageValue < 0 || $salvageValue >= $cost) {
            throw new \InvalidArgumentException('Salvage value must be zero or positive and less than the acquisition cost.');
        }

        return Asset::forceCreate([
            'name' => $name,
            'category' => $category,
            'acquisition_date' => $acquisitionDate,
            'acquisition_cost' => $cost,
            'useful_life_months' => $usefulLifeMonths,
            'salvage_value' => $salvageValue,
            'status' => Asset::STATUS_ACTIVE,
        ]);
    }

    public function monthlyDepreciationAmount(Asset $asset): float
    {
        if (empty($asset->useful_life_months) || $asset->useful_life_months <= 0) {
            return 0;
        }

        return round(((float) $asset->acquisition_cost - (float) $asset->salvage_value) / $asset->useful_life_months, 4);
    }

    /**
     * One depreciation run for one period (usually a calendar month). Idempotent (unique on
     * asset_id+period_date - re-running the same period is a no-op, not a double charge) and stops once the
     * asset is fully depreciated down to its salvage value, capping the final period's amount rather than
     * over-depreciating past it.
     */
    public function runDepreciation(Asset $asset, string $periodDate): ?DepreciationSchedule
    {
        if (DepreciationSchedule::where('asset_id', $asset->id)->where('period_date', $periodDate)->exists()) {
            return null;
        }

        $monthlyAmount = $this->monthlyDepreciationAmount($asset);
        if ($monthlyAmount <= 0) {
            return null;
        }

        $priorAccumulated = (float) (DepreciationSchedule::where('asset_id', $asset->id)->max('accumulated_depreciation') ?? 0);
        $depreciableBase = (float) $asset->acquisition_cost - (float) $asset->salvage_value;
        $remaining = round($depreciableBase - $priorAccumulated, 4);

        if ($remaining <= 0) {
            return null;
        }

        $amount = min($monthlyAmount, $remaining);
        $newAccumulated = round($priorAccumulated + $amount, 4);

        return DB::transaction(function () use ($asset, $periodDate, $amount, $newAccumulated) {
            $entry = app(LedgerService::class)->postEntry(
                "Depreciation for asset #{$asset->id} ({$asset->name}) - {$periodDate}",
                [['account_code' => '5200', 'debit' => $amount], ['account_code' => '1210', 'credit' => $amount]],
                'asset_depreciation',
                $asset->id
            );

            return DepreciationSchedule::forceCreate([
                'asset_id' => $asset->id,
                'period_date' => $periodDate,
                'depreciation_amount' => $amount,
                'accumulated_depreciation' => $newAccumulated,
                'journal_entry_id' => $entry->id,
            ]);
        });
    }
}
