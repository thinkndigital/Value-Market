<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'store_id',
        'slug',
        'parent_id',
        'image',
        'banner',
        'status',
        'style',
        'row_order',
        'clicks'
    ];

    public static function getCategories()
    {
        return static::all();
    }

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::creating(function ($category) {
    //         $category->slug = Str::slug($category->name);
    //         $count = 1;
    //         while (static::whereSlug($category->slug)->exists()) {
    //             $category->slug = Str::slug($category->name) . '-' . $count++;
    //         }
    //     });
    // }

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->where('status', 1);
    }
}
