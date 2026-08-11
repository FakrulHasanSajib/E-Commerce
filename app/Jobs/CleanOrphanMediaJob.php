<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class CleanOrphanMediaJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Select media records created > 24 hours ago that have no entries in mediables
        $orphans = Media::where('created_at', '<', now()->subHours(24))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('mediables')
                    ->whereColumn('mediables.media_id', 'media.id');
            })
            ->get();

        foreach ($orphans as $orphan) {
            // Delete the media record. The model's deleting observer will
            // automatically delete the files and conversions from storage.
            $orphan->delete();
        }
    }
}
