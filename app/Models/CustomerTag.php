<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTag extends Model
{
    protected $fillable = [
        'seller_id', 'name', 'color',
    ];

    public function assignments()
    {
        return $this->hasMany(CustomerTagAssignment::class, 'customer_tag_id');
    }
}
