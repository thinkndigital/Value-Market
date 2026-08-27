<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    const STATUS_INACTIVE = 0;
    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'name', 'email', 'phone', 'ownership_percentage', 'status',
    ];

    public function transactions()
    {
        return $this->hasMany(PartnerTransaction::class, 'partner_id');
    }
}
