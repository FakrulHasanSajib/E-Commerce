<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use App\Services\Return\ReturnService;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function __construct(protected ReturnService $returnService) {}

    /**
     * Customer return requests history.
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum');

        $returns = ReturnRequest::where('user_id', $user->id)
            ->with(['items.orderItem', 'order'])
            ->latest()
            ->paginate(15);

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
     * Customer submits return request.
     */
    public function store(Request $request)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|max:255',
            'refund_method' => 'required|string|in:wallet,bank',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.condition' => 'nullable|string|in:unopened,damaged,defective',
        ]);

        $returnRequest = $this->returnService->createReturnRequest(
            $user,
            $validated['order_id'],
            $validated['reason'],
            $validated['refund_method'],
            $validated['items']
        );

        return response()->json([
            'message' => 'Return request submitted successfully.',
            'data' => $returnRequest->load('items'),
        ], 201);
    }
}
