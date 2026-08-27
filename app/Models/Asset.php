<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_DISPOSED = 'disposed';

    protected $fillable = [
        'name', 'category', 'acquisition_date', 'acquisition_cost',
        'useful_life_months', 'salvage_value', 'status', 'disposed_at',
    ];

    public function depreciationSchedules()
    {
        return $this->hasMany(DepreciationSchedule::class, 'asset_id');
    }
}
