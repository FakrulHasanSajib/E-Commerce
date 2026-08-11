<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Admin\CustomerSegmentationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateCustomerSegmentsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job to chunk user segmentation processing.
     */
    public function handle(CustomerSegmentationService $segmentationService): void
    {
        User::chunk(500, function ($users) use ($segmentationService) {
            foreach ($users as $user) {
                $segmentationService->recalculateForUser($user);
            }
        });
    }
}
