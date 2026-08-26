<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class ComboProductFaq extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'product_id',
        'question',
        'answer',
        'user_id',
        'seller_id',
        'votes',
        'answered_by',
    ];
    public function answeredBy()
    {
        return $this->belongsTo(Seller::class, 'answered_by');
    }

    public function comboProduct()
    {
        return $this->belongsTo(ComboProduct::class);
    }
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'answered_by');
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
