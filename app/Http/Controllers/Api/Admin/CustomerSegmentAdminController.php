<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateCustomerSegmentsJob;
use App\Models\CustomerSegment;

class CustomerSegmentAdminController extends Controller
{
    /**
     * List customer segments.
     */
    public function index()
    {
        $segments = CustomerSegment::with('user')->latest()->paginate(15);

        return response()->json([
            'data' => $segments->items(),
            'meta' => [
                'current_page' => $segments->currentPage(),
                'last_page' => $segments->lastPage(),
                'total' => $segments->total(),
            ],
        ]);
    }

    /**
     * Dispatch queued background job for full user segment recalculations.
     */
    public function recalculate()
    {
        RecalculateCustomerSegmentsJob::dispatch();

        return response()->json([
            'message' => 'Customer segmentation recalculation job dispatched successfully.',
        ], 202);
    }
}
