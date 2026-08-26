<?php

namespace App\Services;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use SplFileInfo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Filesystem as MediaFilesystem;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

class MediaService
{
    public function findMediaType($extenstion)
    {
        $mediaTypes = config('eshop_pro.type');
    
        foreach ($mediaTypes as $mainType => $mediaType) {
            if (in_array(strtolower($extenstion), $mediaType['types'])) {
    
                return [$mainType, $mediaType['icon']];
            }
        }
        return false;
    }

    public function getImageUrl($path, $image_type = '', $image_size = '', $file_type = 'image', $const = 'MEDIA_PATH')
    {

        $pathParts = explode('/', $path);

        $subdirectory = implode("/", array_slice($pathParts, 0, -1));
        $image_name = end($pathParts);
        $file_main_dir = str_replace('\\', '/', public_path(config('constants.' . $const) . $subdirectory));

        if ($file_type == 'image') {


            $types = ['thumb', 'cropped'];
            $sizes = ['md', 'sm'];


            if (in_array(strtolower($image_type), $types) && in_array(strtolower($image_size), $sizes)) {

                $filepath = $file_main_dir . '/' . $image_type . '-' . $image_size . '/' . $image_name;


                if (File::exists($filepath)) {

                    return asset(config('constants.' . $const) . '/' . $path);
                } elseif (File::exists($file_main_dir . '/' . $image_name)) {

                    return asset(config('constants.' . $const) . '/' . $path);
                } else {
                    return asset(Config::get('constants.NO_IMAGE'));
                }
            } else {


                if (File::exists($file_main_dir . '/' . $image_name)) {

                    return asset(config('constants.' . $const) . '/' . $path);
                } else {
                    return asset(Config::get('constants.NO_IMAGE'));
                }
            }
        } else {
            $file = new SplFileInfo($file_main_dir . '/' . $image_name);
            $ext = $file->getExtension();

            $media_data = $this->findMediaType($ext);

            if (is_array($media_data) && isset($media_data[1])) {
                $imagePlaceholder = $media_data[1];
            } else {
                // Handle the case where media type is not found
                return asset(Config::get('constants.NO_IMAGE'));
            }

            $filepath = str_replace('\\', '/', public_path($imagePlaceholder));

            if (File::exists($filepath)) {
                return asset($imagePlaceholder);
            } else {
                return asset(Config::get('constants.NO_IMAGE')); // Assuming 'no_image' is defined in your config
            }
        }
    }

    public function removeMediaFile($path, $disk)
    {


        // Instantiate the Spatie Media Library Filesystem
        $mediaFileSystem = app(MediaFilesystem::class);

        // Instantiate the FilesystemFactory
        $filesystem = app(FilesystemFactory::class);

        // Instantiate the CustomFileRemover with the dependencies
        $fileRemover = new CustomFileRemover($mediaFileSystem, $filesystem);

        if ($disk == 's3') {
            // Get the last two segments of the path
            $path = implode('/', array_slice(explode('/', $path), -2));
        }


        $fileRemover->removeFile($path, $disk);
    }

    public function dynamic_image($image, $width, $quantity = 90)
    {
        return route('front_end.dynamic_image', [
            'url' => $this->getMediaImageUrl($image),
            'width' => $width,
            'quality' => $quantity,
        ]);
    }
    public function getMediaImageUrl($image, $const = 'MEDIA_PATH')
    {
        // check if image url is from s3 or loacl storage and return url according to that
        $imageUrl = !Str::contains($image, 'https:')
            ? (!empty($image) && file_exists(public_path(config('constants.' . $const) . $image)) ? asset(config('constants.' . $const) . $image) : asset(config('constants.NO_IMAGE')))
            : $image;

        return $imageUrl;
    }
}