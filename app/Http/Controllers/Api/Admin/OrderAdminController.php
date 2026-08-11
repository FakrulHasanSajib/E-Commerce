<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderAdminController extends Controller
{
    public function __construct(protected OrderService $orderService) {}

    /**
     * List all orders in the system with optional status filtering.
     */
    public function index(Request $request)
    {
        Gate::authorize('manage-products');

        $query = Order::with('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * Update order status (timeline tracking log + cancellation stock restoration).
     */
    public function updateStatus(Request $request, $id)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'payment_status' => 'nullable|string|in:unpaid,paid,refunded,partial',
            'note' => 'nullable|string|max:255',
        ]);

        $order = Order::findOrFail($id);

        $changedBy = $request->user('sanctum');

        if ($request->filled('payment_status')) {
            $order->payment_status = $validated['payment_status'];
            $order->save();
        }

        if ($request->filled('status')) {
            $this->orderService->updateStatus(
                $order,
                $validated['status'],
                $validated['note'] ?? null,
                $changedBy
            );
        }

        $order->load(['items', 'histories']);

        return new OrderResource($order);
    }
}
