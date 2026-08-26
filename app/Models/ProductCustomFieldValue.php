<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductCustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'product_custom_field_values';

    protected $fillable = [
        'product_id',
        'custom_field_id',
        'value',
    ];


    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customField()
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}