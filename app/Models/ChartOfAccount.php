<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    const TYPE_ASSET = 'asset';
    const TYPE_LIABILITY = 'liability';
    const TYPE_EQUITY = 'equity';
    const TYPE_REVENUE = 'revenue';
    const TYPE_EXPENSE = 'expense';

    /** Debit-normal account types - balance = SUM(debit) - SUM(credit). */
    const DEBIT_NORMAL_TYPES = [self::TYPE_ASSET, self::TYPE_EXPENSE];

    protected $fillable = [
        'code', 'name', 'type', 'parent_id', 'is_system', 'status',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class, 'account_id');
    }
}
