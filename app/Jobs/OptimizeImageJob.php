<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OptimizeImageJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Media $media)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Only optimize images, skip SVG or non-image formats
        if (! str_starts_with($this->media->mime_type, 'image/') || $this->media->mime_type === 'image/svg+xml') {
            return;
        }

        $disk = Storage::disk($this->media->disk);
        $filePath = $this->media->path.'/'.$this->media->filename;

        if (! $disk->exists($filePath)) {
            return;
        }

        $fileContent = $disk->get($filePath);
        $image = @imagecreatefromstring($fileContent);

        if (! $image) {
            return;
        }

        $originalWidth = imagesx($image);
        $originalHeight = imagesy($image);

        // Update main dimensions if not set
        if (empty($this->media->width) || empty($this->media->height)) {
            $this->media->update([
                'width' => $originalWidth,
                'height' => $originalHeight,
            ]);
        }

        $sizes = [
            'thumbnail' => [150, 150],
            'medium' => [600, 600],
            'large' => [1200, 1200],
        ];

        $conversions = [];

        foreach ($sizes as $name => [$maxWidth, $maxHeight]) {
            $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);

            // For thumbnails, perform center cropping (square fit)
            if ($name === 'thumbnail') {
                $newWidth = $maxWidth;
                $newHeight = $maxHeight;
                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);

                $srcRatio = $originalWidth / $originalHeight;
                $destRatio = $newWidth / $newHeight;

                if ($srcRatio >= $destRatio) {
                    $srcHeight = $originalHeight;
                    $srcWidth = (int) ($originalHeight * $destRatio);
                    $srcX = (int) (($originalWidth - $srcWidth) / 2);
                    $srcY = 0;
                } else {
                    $srcWidth = $originalWidth;
                    $srcHeight = (int) ($originalWidth / $destRatio);
                    $srcX = 0;
                    $srcY = (int) (($originalHeight - $srcHeight) / 2);
                }

                imagecopyresampled($newImage, $image, 0, 0, $srcX, $srcY, $newWidth, $newHeight, $srcWidth, $srcHeight);
            } else {
                // For medium/large, maintain original aspect ratio
                if ($ratio < 1) {
                    $newWidth = (int) ($originalWidth * $ratio);
                    $newHeight = (int) ($originalHeight * $ratio);
                } else {
                    $newWidth = $originalWidth;
                    $newHeight = $originalHeight;
                }

                $newImage = imagecreatetruecolor($newWidth, $newHeight);
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            }

            // Save to WebP in memory buffer
            ob_start();
            imagewebp($newImage, null, 85);
            $webpContent = ob_get_clean();

            $pathInfo = pathinfo($this->media->filename);
            $conversionFilename = $pathInfo['filename'].'_'.$name.'.webp';
            $conversionPath = $this->media->path.'/'.$conversionFilename;

            $disk->put($conversionPath, $webpContent);
            imagedestroy($newImage);

            $conversions[$name] = [
                'path' => $conversionPath,
                'width' => $newWidth,
                'height' => $newHeight,
                'size' => strlen($webpContent),
            ];
        }

        imagedestroy($image);

        // Update conversions array
        $this->media->update([
            'conversions' => $conversions,
        ]);

        // Invalidate storage stats cache
        Cache::forget('media:storage_stats');
    }
}
