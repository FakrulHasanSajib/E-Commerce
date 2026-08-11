<?php

namespace App\Services;

use App\Jobs\OptimizeImageJob;
use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    /**
     * Upload a file and create a Media record.
     * Deduplicates files by calculating SHA-256 hashes.
     */
    public function upload(UploadedFile $file, ?int $folderId = null, ?User $uploader = null): Media
    {
        $hash = hash_file('sha256', $file->getRealPath());

        // Deduplication Check: Check if this file already exists in database
        $existing = Media::where('hash', $hash)->first();
        if ($existing) {
            return $existing;
        }

        // Generate unique filename to prevent collisions on disk
        $extension = $file->getClientOriginalExtension();
        if (empty($extension)) {
            $extension = 'bin';
        }
        $filename = Str::random(40).'.'.$extension;

        // Path structured by year/month
        $path = 'media/'.date('Y/m');
        $diskName = 'public';

        // Store file
        $disk = Storage::disk($diskName);
        $disk->putFileAs($path, $file, $filename);

        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();

        // Get dimensions if it's an image
        $width = null;
        $height = null;
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            $dimensions = @getimagesize($file->getRealPath());
            if ($dimensions) {
                $width = $dimensions[0];
                $height = $dimensions[1];
            }
        }

        // Create media record
        $media = Media::create([
            'folder_id' => $folderId,
            'uploaded_by' => $uploader?->id,
            'name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'path' => $path,
            'disk' => $diskName,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'hash' => $hash,
            'conversions' => [],
        ]);

        // Dispatch background optimization job if it's an image
        if ($width && $height) {
            OptimizeImageJob::dispatch($media);
        }

        // Invalidate storage stats cache
        Cache::forget('media:storage_stats');

        return $media;
    }

    /**
     * Crop, resize, and/or rotate an image.
     */
    public function editImage(Media $media, array $params): Media
    {
        if (! str_starts_with($media->mime_type, 'image/') || $media->mime_type === 'image/svg+xml') {
            throw new \InvalidArgumentException('Only raster images can be edited.');
        }

        $disk = Storage::disk($media->disk);
        $filePath = $media->path.'/'.$media->filename;

        if (! $disk->exists($filePath)) {
            throw new \RuntimeException('Original media file not found.');
        }

        $fileContent = $disk->get($filePath);
        $image = @imagecreatefromstring($fileContent);

        if (! $image) {
            throw new \RuntimeException('Failed to load image resource.');
        }

        // 1. Rotation (clockwise angle)
        if (isset($params['rotate']) && $params['rotate'] != 0) {
            // GD imagerotate rotates counter-clockwise, so negate the angle
            $angle = -$params['rotate'];
            $bgColor = imagecolorallocatealpha($image, 0, 0, 0, 127);
            $rotated = imagerotate($image, $angle, $bgColor);
            if ($rotated) {
                imagedestroy($image);
                $image = $rotated;
                imagesavealpha($image, true);
            }
        }

        // 2. Cropping
        if (isset($params['crop_width']) && isset($params['crop_height'])) {
            $cropX = $params['crop_x'] ?? 0;
            $cropY = $params['crop_y'] ?? 0;

            $cropped = imagecrop($image, [
                'x' => (int) $cropX,
                'y' => (int) $cropY,
                'width' => (int) $params['crop_width'],
                'height' => (int) $params['crop_height'],
            ]);

            if ($cropped) {
                imagedestroy($image);
                $image = $cropped;
            }
        }

        // 3. Resizing
        if (isset($params['resize_width']) && isset($params['resize_height'])) {
            $newWidth = (int) $params['resize_width'];
            $newHeight = (int) $params['resize_height'];

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);

            imagecopyresampled(
                $resizedImage,
                $image,
                0, 0, 0, 0,
                $newWidth,
                $newHeight,
                imagesx($image),
                imagesy($image)
            );

            imagedestroy($image);
            $image = $resizedImage;
        }

        // Save back to disk in original format
        ob_start();
        if ($media->mime_type === 'image/png') {
            imagepng($image, null);
        } elseif ($media->mime_type === 'image/webp') {
            imagewebp($image, null, 85);
        } elseif ($media->mime_type === 'image/gif') {
            imagegif($image, null);
        } else {
            imagejpeg($image, null, 85);
        }
        $editedContent = ob_get_clean();
        $disk->put($filePath, $editedContent);

        $newWidth = imagesx($image);
        $newHeight = imagesy($image);
        imagedestroy($image);

        // Delete old conversions from disk
        if ($media->conversions && is_array($media->conversions)) {
            foreach ($media->conversions as $conv) {
                if (isset($conv['path']) && $disk->exists($conv['path'])) {
                    $disk->delete($conv['path']);
                }
            }
        }

        // Update DB record (re-calculates file hash and sets conversions to empty)
        $media->update([
            'width' => $newWidth,
            'height' => $newHeight,
            'size' => strlen($editedContent),
            'hash' => hash('sha256', $editedContent),
            'conversions' => [],
        ]);

        // Re-dispatch background optimization job
        OptimizeImageJob::dispatch($media);

        // Invalidate storage stats cache
        Cache::forget('media:storage_stats');

        return $media->fresh();
    }

    /**
     * Aggregate and cache storage usage statistics.
     */
    public function getStorageStats(): array
    {
        return Cache::remember('media:storage_stats', 86400, function () {
            // Aggregate size of original files
            $originalSize = Media::sum('size');

            // Aggregate size of conversions (optimized sizes)
            $conversionsSize = 0;
            Media::chunk(100, function ($mediaItems) use (&$conversionsSize) {
                foreach ($mediaItems as $media) {
                    if ($media->conversions && is_array($media->conversions)) {
                        foreach ($media->conversions as $conv) {
                            if (isset($conv['size'])) {
                                $conversionsSize += (int) $conv['size'];
                            }
                        }
                    }
                }
            });

            $totalSize = $originalSize + $conversionsSize;
            $fileCount = Media::count();
            $imageCount = Media::where('mime_type', 'like', 'image/%')->count();
            $videoCount = Media::where('mime_type', 'like', 'video/%')->count();
            $docCount = Media::where('mime_type', 'not like', 'image/%')
                ->where('mime_type', 'not like', 'video/%')
                ->count();

            return [
                'total_size' => $totalSize,
                'original_size' => $originalSize,
                'conversions_size' => $conversionsSize,
                'file_count' => $fileCount,
                'image_count' => $imageCount,
                'video_count' => $videoCount,
                'doc_count' => $docCount,
                'storage_limit' => 1024 * 1024 * 1024 * 5, // 5GB default limit
            ];
        });
    }
}
