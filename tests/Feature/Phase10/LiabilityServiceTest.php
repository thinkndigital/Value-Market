<?php

namespace Tests\Feature\Phase10;

use App\Models\Liability;
use App\Services\LedgerService;
use App\Services\LiabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_loan_posts_a_balanced_entry_increasing_cash_and_loans_payable(): void
    {
        $liability = app(LiabilityService::class)->recordLoan('Working capital loan', 5000);

        $this->assertSame(5000.0, (float) $liability->outstanding_balance);
        $this->assertSame(5000.0, app(LedgerService::class)->accountBalance('1000'));
        $this->assertSame(5000.0, app(LedgerService::class)->accountBalance('2200'));
    }

    public function test_record_other_does_not_post_to_the_ledger(): void
    {
        $entriesBefore = \App\Models\JournalEntry::count();

        $liability = app(LiabilityService::class)->recordOther('Accrued utilities', Liability::CATEGORY_ACCRUED_EXPENSE, 300);

        $this->assertSame($entriesBefore, \App\Models\JournalEntry::count());
        $this->assertSame(300.0, (float) $liability->outstanding_balance);
    }

    public function test_record_other_rejects_the_loan_category(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(LiabilityService::class)->recordOther('Not a loan', Liability::CATEGORY_LOAN, 100);
    }

    public function test_a_partial_payment_reduces_the_outstanding_balance_and_keeps_the_liability_active(): void
    {
        $liability = app(LiabilityService::class)->recordLoan('Loan', 1000);

        $updated = app(LiabilityService::class)->recordPayment($liability, 400);

        $this->assertSame(600.0, (float) $updated->outstanding_balance);
        $this->assertSame(Liability::STATUS_ACTIVE, $updated->status);
        $this->assertSame(600.0, app(LedgerService::class)->accountBalance('2200'));
    }

    public function test_paying_off_the_full_balance_marks_the_liability_paid(): void
    {
        $liability = app(LiabilityService::class)->recordLoan('Loan', 500);

        $updated = app(LiabilityService::class)->recordPayment($liability, 500);

        $this->assertSame(0.0, (float) $updated->outstanding_balance);
        $this->assertSame(Liability::STATUS_PAID, $updated->status);
    }

    public function test_overpaying_a_liability_is_rejected(): void
    {
        $liability = app(LiabilityService::class)->recordLoan('Loan', 200);

        $this->expectException(\InvalidArgumentException::class);
        app(LiabilityService::class)->recordPayment($liability, 300);
    }

    public function test_paying_a_non_loan_liability_updates_its_balance_without_posting_to_the_ledger(): void
    {
        // recordOther() never posted an origination entry (its funding source is ambiguous - see its
        // docblock), so recordPayment() must not post a payment entry against it either: doing so would
        // debit Accounts Payable with no matching credit ever recorded, driving it to a nonsensical
        // negative balance. Found by writing this test before trusting the method - see
        // LiabilityService::recordPayment()'s docblock.
        $liability = app(LiabilityService::class)->recordOther('Accrued rent', Liability::CATEGORY_OTHER, 400);
        $entriesBefore = \App\Models\JournalEntry::count();

        $updated = app(LiabilityService::class)->recordPayment($liability, 400);

        $this->assertSame(0.0, (float) $updated->outstanding_balance);
        $this->assertSame(Liability::STATUS_PAID, $updated->status);
        $this->assertSame($entriesBefore, \App\Models\JournalEntry::count());
        $this->assertSame(0.0, app(LedgerService::class)->accountBalance('2000'));
    }
}
