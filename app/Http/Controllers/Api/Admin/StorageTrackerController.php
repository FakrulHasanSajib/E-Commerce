<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StorageStatsResource;
use App\Services\MediaService;

class StorageTrackerController extends Controller
{
    public function __construct(protected MediaService $mediaService)
    {
        //
    }

    /**
     * Get storage utilization statistics.
     */
    public function stats(): StorageStatsResource
    {
        $stats = $this->mediaService->getStorageStats();

        return new StorageStatsResource($stats);
    }
}
