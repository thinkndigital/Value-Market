<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerNote extends Model
{
    protected $fillable = [
        'customer_user_id', 'seller_id', 'author_user_id', 'note',
    ];
}
