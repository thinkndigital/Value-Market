<?php

namespace Tests\Feature\Phase10;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Services\LedgerService;
use App\Services\PartnerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makePartner(): Partner
    {
        return Partner::forceCreate(['name' => 'Partner ' . uniqid(), 'status' => Partner::STATUS_ACTIVE]);
    }

    public function test_a_contribution_posts_a_balanced_ledger_entry_and_is_tracked(): void
    {
        $partner = $this->makePartner();

        $txn = app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_CONTRIBUTION, 1000, 'Initial capital');

        $this->assertNotNull($txn->journal_entry_id);
        $this->assertSame(1000.0, app(PartnerService::class)->capitalBalance($partner->id));
        $this->assertSame(1000.0, app(LedgerService::class)->accountBalance('1000'));
    }

    public function test_a_withdrawal_decreases_the_capital_balance_and_posts_the_opposite_entry(): void
    {
        $partner = $this->makePartner();
        app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_CONTRIBUTION, 1000);

        $txn = app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_WITHDRAWAL, 300);

        $this->assertNotNull($txn->journal_entry_id);
        $this->assertSame(700.0, app(PartnerService::class)->capitalBalance($partner->id));
        $this->assertSame(700.0, app(LedgerService::class)->accountBalance('1000'));
    }

    public function test_profit_share_updates_capital_balance_without_posting_to_the_ledger(): void
    {
        $partner = $this->makePartner();
        $entriesBefore = \App\Models\JournalEntry::count();

        $txn = app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_PROFIT_SHARE, 250);

        $this->assertNull($txn->journal_entry_id);
        $this->assertSame($entriesBefore, \App\Models\JournalEntry::count());
        $this->assertSame(250.0, app(PartnerService::class)->capitalBalance($partner->id));
    }

    public function test_loss_share_decreases_the_capital_balance(): void
    {
        $partner = $this->makePartner();
        app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_CONTRIBUTION, 1000);

        app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_LOSS_SHARE, 150);

        $this->assertSame(850.0, app(PartnerService::class)->capitalBalance($partner->id));
    }

    public function test_a_zero_or_negative_amount_is_rejected(): void
    {
        $partner = $this->makePartner();

        $this->expectException(\InvalidArgumentException::class);
        app(PartnerService::class)->recordTransaction($partner->id, PartnerTransaction::TYPE_CONTRIBUTION, 0);
    }

    public function test_capital_balances_of_two_partners_do_not_leak_into_each_other(): void
    {
        $partnerA = $this->makePartner();
        $partnerB = $this->makePartner();
        app(PartnerService::class)->recordTransaction($partnerA->id, PartnerTransaction::TYPE_CONTRIBUTION, 500);
        app(PartnerService::class)->recordTransaction($partnerB->id, PartnerTransaction::TYPE_CONTRIBUTION, 800);

        $this->assertSame(500.0, app(PartnerService::class)->capitalBalance($partnerA->id));
        $this->assertSame(800.0, app(PartnerService::class)->capitalBalance($partnerB->id));
    }
}
