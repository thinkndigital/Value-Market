<?php

namespace Tests\Feature\Phase6;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosShift;
use App\Models\Role;
use App\Models\Seller;
use App\Models\User;
use App\Services\PosShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

    private function makeOrder(float $totalPayable): Order
    {
        return Order::forceCreate([
            'user_id' => 1, 'mobile' => (string) random_int(6000000000, 6999999999), 'total' => $totalPayable,
            'total_payable' => $totalPayable, 'payment_method' => 'cash', 'order_payment_currency_id' => 1,
            'order_payment_currency_code' => 'USD', 'base_currency_code' => 'USD',
            'order_payment_currency_conversion_rate' => 1,
        ]);
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 6): a requested shift id used to be
     * trusted as long as it was OPEN, with no check it belonged to the acting sale's own seller. A
     * malicious cashier could pass another seller's open shift id and have their sale's cash posted into
     * it, corrupting that shift's cash reconciliation.
     */
    public function test_a_requested_shift_belonging_to_a_different_seller_is_not_used(): void
    {
        [$owner, $ownerCashier] = $this->makeSeller();
        [$stranger, $strangerCashier] = $this->makeSeller();
        $strangerShift = app(PosShiftService::class)->open($stranger->id, null, $strangerCashier->id, 100.00);

        Auth::login($ownerCashier);
        $order = $this->makeOrder(50.0);

        app(PosShiftService::class)->recordSaleForOpenShift($order, $strangerShift->id, null, $owner->id);

        $this->assertNull($order->fresh()->pos_shift_id);
        $this->assertSame(0, PosPayment::where('pos_shift_id', $strangerShift->id)->count());
    }

    public function test_a_requested_shift_belonging_to_the_same_seller_is_used(): void
    {
        [$owner, $cashierA] = $this->makeSeller();
        $cashierB = User::forceCreate([
            'username' => 'cashier_' . uniqid(), 'password' => 'x', 'disk' => 'public',
            'serviceable_cities' => '', 'type' => 'phone', 'role_id' => Role::SELLER,
        ]);
        $shift = app(PosShiftService::class)->open($owner->id, null, $cashierA->id, 100.00);

        Auth::login($cashierB);
        $order = $this->makeOrder(50.0);

        app(PosShiftService::class)->recordSaleForOpenShift($order, $shift->id, null, $owner->id);

        $this->assertSame($shift->id, $order->fresh()->pos_shift_id);
    }

    /**
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 7): a caller-supplied payments split used
     * to be trusted verbatim with no check the lines actually summed to the order total - a cashier could
     * under-report cash received, invisibly, since shift variance is computed purely from what's recorded
     * in pos_payments.
     */
    public function test_a_payments_split_that_under_reports_the_order_total_is_discarded(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        Auth::login($cashier);
        $order = $this->makeOrder(100.0);

        app(PosShiftService::class)->recordSaleForOpenShift($order, null, [
            ['payment_method' => 'cash', 'amount' => 60.0], // only 60 of a 100 order reported
        ], $seller->id);

        // Falls back to the single trustworthy line: the full order total, not the under-reported split.
        $payments = PosPayment::where('order_id', $order->id)->get();
        $this->assertSame(1, $payments->count());
        $this->assertSame(100.0, (float) $payments->first()->amount);
    }

    public function test_a_payments_split_that_correctly_sums_to_the_order_total_is_recorded_as_given(): void
    {
        [$seller, $cashier] = $this->makeSeller();
        Auth::login($cashier);
        $order = $this->makeOrder(100.0);

        app(PosShiftService::class)->recordSaleForOpenShift($order, null, [
            ['payment_method' => 'cash', 'amount' => 60.0],
            ['payment_method' => 'card', 'amount' => 40.0],
        ], $seller->id);

        $payments = PosPayment::where('order_id', $order->id)->get();
        $this->assertSame(2, $payments->count());
        $this->assertSame(100.0, (float) $payments->sum('amount'));
    }
}
