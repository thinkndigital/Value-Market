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

    // Seller category-request lifecycle (docs/CHANGELOG_FEATURE_AUDIT.md v1.0.6/v1.0.11), tracked in
    // `approval_status` alongside the pre-existing `status` (0/1/2) convention already used for products
    // (see Admin\ProductController::update_status()) - `status` drives every existing "is this usable"
    // query (2 = pending is never selected by `where('status', 1)`), `approval_status` exists only so a
    // rejected request can stay visible/distinguishable in the seller's own request history instead of
    // looking identical to "admin deactivated an approved category".
    const APPROVAL_PENDING = 'pending';
    const APPROVAL_APPROVED = 'approved';
    const APPROVAL_REJECTED = 'rejected';

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
        'clicks',
        'requested_by_seller_id',
        'approval_status',
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
