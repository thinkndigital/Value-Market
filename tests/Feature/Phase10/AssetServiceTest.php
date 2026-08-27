<?php

namespace Tests\Feature\Phase10;

use App\Models\DepreciationSchedule;
use App\Services\AssetService;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_asset_creates_the_record_without_posting_to_the_ledger(): void
    {
        $entriesBefore = \App\Models\JournalEntry::count();

        $asset = app(AssetService::class)->registerAsset('Delivery Van', 'vehicle', 24000, '2026-01-01', 24, 0);

        $this->assertNotNull($asset->id);
        $this->assertSame($entriesBefore, \App\Models\JournalEntry::count());
    }

    public function test_monthly_depreciation_amount_is_straight_line(): void
    {
        $asset = app(AssetService::class)->registerAsset('Laptop', 'equipment', 2400, '2026-01-01', 24, 0);

        $this->assertSame(100.0, app(AssetService::class)->monthlyDepreciationAmount($asset));
    }

    public function test_an_asset_with_no_useful_life_has_zero_monthly_depreciation(): void
    {
        $asset = app(AssetService::class)->registerAsset('Land', 'land', 50000, '2026-01-01', null, 0);

        $this->assertSame(0.0, app(AssetService::class)->monthlyDepreciationAmount($asset));
    }

    public function test_run_depreciation_posts_a_balanced_entry_and_records_the_schedule(): void
    {
        $asset = app(AssetService::class)->registerAsset('Laptop', 'equipment', 2400, '2026-01-01', 24, 0);

        $schedule = app(AssetService::class)->runDepreciation($asset, '2026-02-01');

        $this->assertNotNull($schedule);
        $this->assertSame(100.0, (float) $schedule->depreciation_amount);
        $this->assertSame(100.0, (float) $schedule->accumulated_depreciation);
        // Modeled as a credit-normal 'liability'-type account (see the migration's docblock on why) -
        // a credit-side posting here correctly shows as a positive balance under that convention.
        $this->assertSame(100.0, app(LedgerService::class)->accountBalance('1210'));
        $this->assertSame(100.0, app(LedgerService::class)->accountBalance('5200'));
    }

    public function test_running_the_same_period_twice_is_a_no_op(): void
    {
        $asset = app(AssetService::class)->registerAsset('Laptop', 'equipment', 2400, '2026-01-01', 24, 0);
        app(AssetService::class)->runDepreciation($asset, '2026-02-01');

        $second = app(AssetService::class)->runDepreciation($asset, '2026-02-01');

        $this->assertNull($second);
        $this->assertSame(1, DepreciationSchedule::where('asset_id', $asset->id)->count());
    }

    public function test_depreciation_stops_once_the_asset_is_fully_depreciated_to_salvage_value(): void
    {
        // 200 cost, 0 salvage, 2-month life -> 100/month, fully depreciated after 2 runs.
        $asset = app(AssetService::class)->registerAsset('Tool', 'equipment', 200, '2026-01-01', 2, 0);

        app(AssetService::class)->runDepreciation($asset, '2026-02-01');
        app(AssetService::class)->runDepreciation($asset, '2026-03-01');
        $third = app(AssetService::class)->runDepreciation($asset, '2026-04-01');

        $this->assertNull($third);
        $this->assertSame(200.0, (float) DepreciationSchedule::where('asset_id', $asset->id)->max('accumulated_depreciation'));
    }

    public function test_registering_an_asset_with_salvage_value_at_or_above_cost_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AssetService::class)->registerAsset('Bad Asset', 'equipment', 1000, '2026-01-01', 12, 1000);
    }
}
