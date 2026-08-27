<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTagAssignment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_user_id', 'customer_tag_id', 'assigned_by', 'created_at',
    ];

    public function tag()
    {
        return $this->belongsTo(CustomerTag::class, 'customer_tag_id');
    }
}
