<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Liability extends Model
{
    const CATEGORY_LOAN = 'loan';
    const CATEGORY_ACCRUED_EXPENSE = 'accrued_expense';
    const CATEGORY_OTHER = 'other';

    const STATUS_ACTIVE = 'active';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'name', 'category', 'principal_amount', 'outstanding_balance', 'due_date', 'status',
    ];
}
