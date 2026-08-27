<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PosPayment;
use App\Models\PosShift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6 (docs/PHASE_6_POS.md): till shifts (open/close with cash reconciliation) and per-order payment
 * lines. Split payments are additive on top of orders.payment_method (which stays as the primary/first
 * method for every existing report/query that already reads it) - pos_payments is a richer breakdown, not a
 * replacement.
 */
class PosShiftService
{
    public function open(int $sellerId, ?int $branchId, int $userId, float $openingCash, ?string $notes = null): PosShift
    {
        $alreadyOpen = PosShift::where('user_id', $userId)->where('status', PosShift::STATUS_OPEN)->exists();
        if ($alreadyOpen) {
            throw new \InvalidArgumentException('This cashier already has an open shift.');
        }

        return PosShift::forceCreate([
            'seller_id' => $sellerId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'opening_cash' => $openingCash,
            'status' => PosShift::STATUS_OPEN,
            'notes' => $notes,
            'opened_at' => now(),
        ]);
    }

    public function close(PosShift $shift, float $closingCash, ?string $notes = null): PosShift
    {
        if ($shift->status !== PosShift::STATUS_OPEN) {
            throw new \InvalidArgumentException('This shift is already closed.');
        }

        return DB::transaction(function () use ($shift, $closingCash, $notes) {
            $cashSales = PosPayment::where('pos_shift_id', $shift->id)
                ->where('payment_method', PosPayment::METHOD_CASH)
                ->sum('amount');

            $expectedCash = (float) $shift->opening_cash + (float) $cashSales;

            $shift->closing_cash = $closingCash;
            $shift->expected_cash = $expectedCash;
            $shift->cash_variance = round($closingCash - $expectedCash, 4);
            $shift->status = PosShift::STATUS_CLOSED;
            $shift->closed_at = now();
            if ($notes !== null) {
                $shift->notes = $notes;
            }
            $shift->save();

            return $shift;
        });
    }

    public function activeShiftFor(int $userId): ?PosShift
    {
        return PosShift::where('user_id', $userId)->where('status', PosShift::STATUS_OPEN)->first();
    }

    /**
     * Called after a POS order is created: attaches it to an open shift (the requested one if valid and
     * open, otherwise the cashier's own active shift, otherwise none - a POS sale is never blocked just
     * because no shift is open, since shift tracking is additive on top of the existing sale flow) and
     * records its payment breakdown. When $payments isn't given (every pre-Phase-6 caller), records a
     * single payment line from the order's own payment_method/total_payable so reconciliation still has
     * something to sum, without requiring POS callers to change how they call place_order().
     *
     * Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 6): $requestedShiftId used to be trusted
     * as long as it was OPEN, with no check it belonged to the acting seller's tenant. A malicious cashier
     * could pass another seller's (or another seller's cashier's) open shift id and have their own sale's
     * cash/payment lines posted into it, corrupting that shift's cash reconciliation at close time. $sellerId
     * (the tenant the sale actually belongs to) is now required to match on both the requested-shift lookup
     * and the cashier's-own-active-shift fallback.
     */
    public function recordSaleForOpenShift(Order $order, $requestedShiftId = null, $payments = null, ?int $sellerId = null): void
    {
        $shift = null;
        if (!empty($requestedShiftId)) {
            $shift = PosShift::where('id', $requestedShiftId)
                ->where('status', PosShift::STATUS_OPEN)
                ->when($sellerId !== null, fn ($q) => $q->where('seller_id', $sellerId))
                ->first();
        }
        if (!$shift && Auth::id()) {
            // $order->user_id is the customer, not the cashier - the acting cashier is whoever is
            // authenticated in this request (the seller/employee running the POS terminal).
            $shift = $this->activeShiftFor((int) Auth::id());
            if ($shift && $sellerId !== null && (int) $shift->seller_id !== $sellerId) {
                $shift = null;
            }
        }

        if ($shift) {
            $order->pos_shift_id = $shift->id;
            $order->save();
        }

        // Security audit finding (docs/SECURITY_AUDIT.md §6, Finding 7): a caller-supplied $payments split
        // used to be trusted verbatim, with no check its lines actually summed to the order total - a
        // cashier could report e.g. only 60 of a 100 cash sale, pocketing the difference invisibly (shift
        // variance is computed purely from what's in pos_payments, so an under-reported split just never
        // shows as a discrepancy at close time). If the supplied lines don't sum to total_payable (small
        // float-rounding tolerance aside), the whole split is discarded and the same single-line fallback
        // used when no split is given at all is recorded instead - a sale is never blocked by a bad split,
        // it's just not allowed to under-report.
        $suppliedSum = is_array($payments) ? array_sum(array_map(fn ($l) => (float) ($l['amount'] ?? 0), $payments)) : null;
        $paymentsAreTrustworthy = is_array($payments) && !empty($payments) && abs($suppliedSum - (float) $order->total_payable) < 0.01;

        $lines = $paymentsAreTrustworthy ? $payments : [
            ['payment_method' => $order->payment_method ?: 'cash', 'amount' => (float) $order->total_payable],
        ];

        foreach ($lines as $line) {
            if (empty($line['amount']) || (float) $line['amount'] <= 0) {
                continue;
            }
            PosPayment::forceCreate([
                'order_id' => $order->id,
                'pos_shift_id' => $shift?->id,
                'payment_method' => $line['payment_method'] ?? ($order->payment_method ?: 'cash'),
                'amount' => (float) $line['amount'],
            ]);
        }
    }

}
