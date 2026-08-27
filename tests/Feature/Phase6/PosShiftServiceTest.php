<?php

namespace Tests\Feature\Phase6;

use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\PosShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosShiftServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSeller(): array
    {
        $sellerUser = User::forceCreate([
            'username' => 'seller_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $seller = Seller::forceCreate(['user_id' => $sellerUser->id, 'disk' => 'public', 'status' => 1]);

        return [$seller, $sellerUser];
    }

    public function test_open_creates_a_shift_in_open_status(): void
    {
        [$seller, $cashier] = $this->makeSeller();

        $shift = app(PosShiftService::class)->open($seller->id, null, $cashier->id, 100.00);

        $this->assertSame(PosShift::STATUS_OPEN, $shift->status);
        $this->assertSame(100.0, (float) $shift->opening_cash);
    }

    public function test_a_cashier_cannot_open_a_second_shift_while_one_is_already_open(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        app(PosShiftService::class)->open($seller->id, null, $cashier->id, 100.00);

        $this->expectException(\InvalidArgumentException::class);
        app(PosShiftService::class)->open($seller->id, null, $cashier->id, 50.00);
    }

    public function test_close_computes_expected_cash_from_opening_plus_cash_payments_only(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        $shift = app(PosShiftService::class)->open($seller->id, null, $cashier->id, 100.00);

        PosPayment::forceCreate(['order_id' => 1, 'pos_shift_id' => $shift->id, 'payment_method' => 'cash', 'amount' => 40]);
        PosPayment::forceCreate(['order_id' => 2, 'pos_shift_id' => $shift->id, 'payment_method' => 'cash', 'amount' => 20]);
        PosPayment::forceCreate(['order_id' => 3, 'pos_shift_id' => $shift->id, 'payment_method' => 'card', 'amount' => 500]);

        $closed = app(PosShiftService::class)->close($shift, closingCash: 160.00);

        $this->assertSame(PosShift::STATUS_CLOSED, $closed->status);
        $this->assertSame(160.0, (float) $closed->expected_cash); // 100 opening + 40 + 20 cash, card excluded
        $this->assertSame(0.0, (float) $closed->cash_variance);
    }

    public function test_close_records_a_negative_variance_when_the_till_is_short(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        $shift = app(PosShiftService::class)->open($seller->id, null, $cashier->id, 100.00);
        PosPayment::forceCreate(['order_id' => 1, 'pos_shift_id' => $shift->id, 'payment_method' => 'cash', 'amount' => 50]);

        $closed = app(PosShiftService::class)->close($shift, closingCash: 140.00); // expected 150, counted 140

        $this->assertSame(-10.0, (float) $closed->cash_variance);
    }

    public function test_a_closed_shift_cannot_be_closed_again(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        $shift = app(PosShiftService::class)->open($seller->id, null, $cashier->id, 100.00);
        app(PosShiftService::class)->close($shift, closingCash: 100.00);

        $this->expectException(\InvalidArgumentException::class);
        app(PosShiftService::class)->close($shift->fresh(), closingCash: 100.00);
    }
}
