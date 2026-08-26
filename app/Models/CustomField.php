<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomField extends Model
{
    use HasFactory;

    protected $table = 'custom_fields';

    protected $fillable = [
        'store_id',
        'name',
        'type',
        'field_length',
        'min',
        'max',
        'required',
        'active',
        'options',
    ];

    protected $casts = [
        'required' => 'boolean',
        'active' => 'boolean',
        'options' => 'array', 
    ];

    /**
     * Get the store this custom field belongs to.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function productCustomFieldValues()
    {
        return $this->hasMany(ProductCustomFieldValue::class, 'custom_field_id');
    }
}
