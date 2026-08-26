<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;



use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Media extends Model implements HasMedia
{
    protected $table = 'media';
    use HasApiTokens, HasFactory, Notifiable, InteractsWithMedia;
    protected $fillable = [
        'seller_id',
        'store_id',
        'title',
        'height',
        'width',
        'name',
        'extension',
        'type',
        'sub_directory',
        'size',
        'order_column',
        'model_type',
        'model_id',
        'file_name',
        'disk',
        'conversions_disk',
        'collection_name',
        'mime_type',
        'custom_properties',
        'generated_conversions',
        'responsive_images',
        'manipulations',
        'uuid',
        'object_url',
    ];

    public function registerMediaCollections(): void
    {
        $media_storage_settings = fetchDetails(StorageType::class, ['is_default' => 1], '*');
        $mediaStorageType = !$media_storage_settings->isEmpty() ? $media_storage_settings[0]->name : 'public';
        if ($mediaStorageType == 's3') {
            $this->addMediaCollection('media')->useDisk('s3');
        } else {
            $this->addMediaCollection('media')->useDisk('public');
        }
    }
}
