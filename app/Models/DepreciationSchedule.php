<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DepreciationSchedule extends Model
{
    protected $fillable = [
        'asset_id', 'period_date', 'depreciation_amount', 'accumulated_depreciation', 'journal_entry_id',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }
}
