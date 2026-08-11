<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceFingerprint;

class DeviceAdminController extends Controller
{
    /**
     * List tracked device fingerprints.
     */
    public function devices()
    {
        $devices = DeviceFingerprint::with('user')->latest('last_seen_at')->paginate(15);

        return response()->json([
            'data' => $devices->items(),
            'meta' => [
                'current_page' => $devices->currentPage(),
                'last_page' => $devices->lastPage(),
                'total' => $devices->total(),
            ],
        ]);
    }
}
