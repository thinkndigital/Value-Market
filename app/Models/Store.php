<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Store extends Model implements HasMedia
{

    use InteractsWithMedia;
    protected $casts = [
        'store_settings' => 'array',
    ];
    use HasApiTokens, HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'image',
        'description',
        'banner_image',
        'is_single_seller_order_system',
        'is_default_store',
        'status',
        'primary_color',
        'secondary_color',
        'hover_color',
        'active_color',
        'on_boarding_image',
        'on_boarding_video',
        'banner_image_for_most_selling_product',
        'stack_image',
        'login_image',
        'half_store_logo',
        'store_settings',
        'disk',
        'delivery_charge_type',
        'delivery_charge_amount',
        'minimum_free_delivery_amount',
        'product_deliverability_type',
    ];


    // public function sellers()
    // {
    //     return $this->belongsToMany(Seller::class);
    // }
    public function sellers()
    {
        return $this->belongsToMany(Seller::class, 'seller_store', 'store_id', 'seller_id')
            ->withPivot('logo', 'store_name', 'category_ids', 'deliverable_type', 'user_id', 'rating', 'no_of_ratings');
    }
    public function registerMediaCollections(): void
    {
        $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
        $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
        if ($mediaStorageType === 's3') {
            $this->addMediaCollection('store_images')->useDisk('s3');
        } else {
            $this->addMediaCollection('store_images')->useDisk('public');
        }
    }
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function customFields()
    {
        return $this->hasMany(CustomField::class, 'store_id', 'id');
    }
}
