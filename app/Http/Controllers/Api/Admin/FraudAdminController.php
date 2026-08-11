<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\Order;
use App\Services\Admin\AuditLogService;
use App\Services\Admin\FraudDetectionService;
use Illuminate\Http\Request;

class FraudAdminController extends Controller
{
    public function __construct(
        protected FraudDetectionService $fraudDetectionService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List blacklists.
     */
    public function blacklists()
    {
        $blacklists = Blacklist::with('addedBy')->latest()->paginate(15);

        return response()->json([
            'data' => $blacklists->items(),
            'meta' => [
                'current_page' => $blacklists->currentPage(),
                'last_page' => $blacklists->lastPage(),
                'total' => $blacklists->total(),
            ],
        ]);
    }

    /**
     * Add phone, IP, or email to blacklist.
     */
    public function storeBlacklist(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:phone,ip,email',
            'value' => 'required|string|max:255',
            'reason' => 'nullable|string',
        ]);

        $blacklist = $this->fraudDetectionService->addToBlacklist(
            $validated['type'],
            $validated['value'],
            $validated['reason'] ?? null,
            $request->user()?->id
        );

        $this->auditLogService->log(
            'blacklist.added',
            $blacklist,
            ['type' => $blacklist->type, 'value' => $blacklist->value],
            "Added {$blacklist->type} {$blacklist->value} to blacklist."
        );

        return response()->json([
            'message' => 'Added to blacklist successfully.',
            'data' => $blacklist,
        ], 201);
    }

    /**
     * Delete from blacklist.
     */
    public function destroyBlacklist($id)
    {
        $blacklist = Blacklist::findOrFail($id);

        $this->auditLogService->log(
            'blacklist.removed',
            $blacklist,
            ['type' => $blacklist->type, 'value' => $blacklist->value],
            "Removed {$blacklist->type} {$blacklist->value} from blacklist."
        );

        $this->fraudDetectionService->removeFromBlacklist($id);

        return response()->json(null, 204);
    }

    /**
     * List flagged fraud orders.
     */
    public function flaggedOrders()
    {
        $orders = Order::where('is_flagged_fraud', true)->latest()->paginate(15);

        return response()->json([
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }
}
