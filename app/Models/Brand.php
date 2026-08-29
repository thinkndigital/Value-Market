<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Brand extends Model
{
    // See App\Models\Category's identical constants for the full explanation - same seller-request
    // lifecycle, same reuse of the pre-existing `status` (0/1/2) convention.
    const APPROVAL_PENDING = 'pending';
    const APPROVAL_APPROVED = 'approved';
    const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'name',
        'store_id',
        'image',
        'slug',
        'status',
        'requested_by_seller_id',
        'approval_status',
    ];

}
