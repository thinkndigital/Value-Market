<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    const STATUS_OPEN = 'open';
    const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'seller_id', 'branch_id', 'user_id', 'opening_cash', 'closing_cash',
        'expected_cash', 'cash_variance', 'status', 'notes', 'opened_at', 'closed_at',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(PosPayment::class, 'pos_shift_id');
    }
}
