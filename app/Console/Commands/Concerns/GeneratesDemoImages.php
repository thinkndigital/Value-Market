<?php

namespace App\Console\Commands\Concerns;

use App\Models\StorageType;
use Illuminate\Support\Str;

/**
 * Shared by the demo:create-* commands (seller/affiliate/delivery boy). A demo account is only useful if
 * its avatar/product/store images actually render instead of falling back to the "no image" placeholder
 * (MediaService::getMediaImageUrl() checks the stored value either starts with "https:" or resolves to a
 * real file on disk) - so these images go through the exact same upload path the app's own controllers use
 * (StorageType::addMedia()->toMediaCollection(), respecting whatever disk is actually configured - local
 * 'public' in dev, 's3' in production), rather than writing a fake path string that would silently 404.
 *
 * Generates simple solid-color placeholder PNGs with GD (already a required extension for this app's own
 * image thumbnailing) - no internet access needed, so this works identically whether run locally or as a
 * one-off Cloud Run Job with no outbound access to an image host.
 */
trait GeneratesDemoImages
{
    private function generatePlaceholderImage(string $label, string $bgColorHex, int $width = 640, int $height = 640): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'demo_img_') . '.png';

        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = sscanf($bgColorHex, '#%02x%02x%02x');
        $bgColor = imagecolorallocate($image, $r, $g, $b);
        imagefill($image, 0, 0, $bgColor);

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $fontSize = 5; // GD's largest built-in bitmap font
        $lines = explode("\n", wordwrap($label, 14, "\n"));
        $lineHeight = imagefontheight($fontSize) + 6;
        $startY = (int) (($height - count($lines) * $lineHeight) / 2);

        foreach ($lines as $i => $line) {
            $textWidth = imagefontwidth($fontSize) * strlen($line);
            $x = (int) (($width - $textWidth) / 2);
            $y = $startY + $i * $lineHeight;
            imagestring($image, $fontSize, $x, $y, $line, $textColor);
        }

        imagepng($image, $tmpPath);
        imagedestroy($image);

        return $tmpPath;
    }

    /**
     * Uploads a locally-generated image through the real media pipeline and returns a fully-qualified URL
     * (works for both the 'public' and 's3' disks - MediaService::getMediaImageUrl() passes any value
     * starting with "https:" straight through, so a full URL is always correct regardless of which disk is
     * actually configured).
     */
    private function uploadDemoImage(string $label, string $bgColorHex, string $collection = 'media'): string
    {
        $localPath = $this->generatePlaceholderImage($label, $bgColorHex);

        try {
            $storageSetting = StorageType::where('is_default', 1)->first();
            $disk = $storageSetting->name ?? 'public';
            $mediaModel = StorageType::find($storageSetting->id ?? 1);

            $mediaItem = $mediaModel->addMedia($localPath)
                ->usingFileName('demo-' . Str::random(10) . '.png')
                ->toMediaCollection($collection, $disk);

            return $mediaItem->getFullUrl();
        } finally {
            if (file_exists($localPath)) {
                unlink($localPath);
            }
        }
    }
}
