<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerSegment extends Model
{
    protected $fillable = [
        'seller_id', 'name', 'criteria',
    ];
}
