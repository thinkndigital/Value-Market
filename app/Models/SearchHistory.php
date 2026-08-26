<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    protected $table = 'search_history';

    protected $fillable = [
        'search_term',
        'store_id',
        'clicks',
    ];

    public $timestamps = true;
}
