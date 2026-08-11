<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PickupPointResource;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Services\Logistics\LogisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LogisticsAdminController extends Controller
{
    public function __construct(protected LogisticsService $logisticsService) {}

    /**
     * Lists all pickup points for admin panel.
     */
    public function index(Request $request)
    {
        Gate::authorize('manage-products');

        $query = PickupPoint::query();

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $points = $query->latest()->paginate(20);

        return PickupPointResource::collection($points);
    }

    /**
     * Creates a new pickup point.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:50',
            'address' => 'required|string',
            'division' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'upazila' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $point = PickupPoint::create($validated);

        return new PickupPointResource($point);
    }

    /**
     * Updates an existing pickup point.
     */
    public function update(Request $request, $id)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:50',
            'address' => 'required|string',
            'division' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'upazila' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $point = PickupPoint::findOrFail($id);
        $point->update($validated);

        return new PickupPointResource($point);
    }

    /**
     * Deletes a pickup point.
     */
    public function destroy($id)
    {
        Gate::authorize('manage-products');

        $point = PickupPoint::findOrFail($id);
        $point->delete();

        return response()->json(null, 204);
    }

    /**
     * Dispatch order packages via courier.
     */
    public function dispatchOrder(Request $request, $id)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'pickup_point_id' => 'required|integer|exists:pickup_points,id',
            'courier_name' => 'required|string|in:steadfast,pathao,paperfly,redx',
        ]);

        $order = Order::findOrFail($id);

        $this->logisticsService->dispatchOrder(
            $order,
            $validated['pickup_point_id'],
            $validated['courier_name']
        );

        return response()->json([
            'message' => 'Order package dispatched successfully.',
            'data' => [
                'tracking_code' => $order->fresh()->courier_tracking_code,
                'status' => $order->fresh()->status,
            ],
        ], 200);
    }

    /**
     * Initiates return reverse logistics pickup.
     */
    public function reversePickup(Request $request, $id)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'pickup_point_id' => 'required|integer|exists:pickup_points,id',
        ]);

        $order = Order::findOrFail($id);

        $this->logisticsService->requestReversePickup($order, $validated['pickup_point_id']);

        return response()->json([
            'message' => 'Reverse pickup shipment request scheduled successfully.',
        ], 200);
    }
}
