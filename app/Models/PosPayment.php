<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 6 (docs/PHASE_6_POS.md): one payment line against a POS order. A single order can have more than
 * one row (split payment - e.g. part cash, part card) summing to the order's total_payable.
 */
class PosPayment extends Model
{
    const METHOD_CASH = 'cash';

    protected $fillable = [
        'order_id', 'pos_shift_id', 'payment_method', 'amount',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function shift()
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }
}
