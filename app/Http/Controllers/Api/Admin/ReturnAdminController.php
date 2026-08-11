<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Return\ReturnService;
use Illuminate\Http\Request;

class ReturnAdminController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    /**
     * Admin list return requests.
     */
    public function index(Request $request)
    {
        $status = $request->input('status');

        $query = ReturnRequest::with(['user', 'order', 'items.orderItem'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $returns = $query->paginate(15);

        return response()->json([
            'data' => $returns->items(),
            'meta' => [
                'current_page' => $returns->currentPage(),
                'last_page' => $returns->lastPage(),
                'total' => $returns->total(),
            ],
        ]);
    }

    /**
     * Admin process QC status inspection.
     */
    public function updateQc(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:qc_passed,qc_failed,rejected',
            'qc_notes' => 'nullable|string',
            'admin_notes' => 'nullable|string',
        ]);

        $returnRequest = $this->returnService->processQcStatus(
            (int) $id,
            $validated['status'],
            $validated['qc_notes'] ?? null,
            $validated['admin_notes'] ?? null
        );

        return response()->json([
            'message' => "Return request processed as {$returnRequest->status}.",
            'data' => $returnRequest,
        ], 200);
    }
}
