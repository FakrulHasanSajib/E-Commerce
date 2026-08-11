<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StorageStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalSize = $this['total_size'] ?? 0;
        $storageLimit = $this['storage_limit'] ?? (1024 * 1024 * 1024 * 5);

        return [
            'total_size' => $totalSize,
            'original_size' => $this['original_size'] ?? 0,
            'conversions_size' => $this['conversions_size'] ?? 0,
            'file_count' => $this['file_count'] ?? 0,
            'image_count' => $this['image_count'] ?? 0,
            'video_count' => $this['video_count'] ?? 0,
            'doc_count' => $this['doc_count'] ?? 0,
            'storage_limit' => $storageLimit,
            'usage_percentage' => round(($totalSize / $storageLimit) * 100, 2),
        ];
    }
}
