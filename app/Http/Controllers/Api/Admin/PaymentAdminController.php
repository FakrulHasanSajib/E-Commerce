<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankPaymentSubmittal;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentAdminController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * List bank transfer submittals with status filtering.
     */
    public function indexBankSubmittals(Request $request)
    {
        Gate::authorize('manage-products');

        $query = BankPaymentSubmittal::with('order');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $submittals = $query->latest()->paginate(20);

        return response()->json($submittals, 200);
    }

    /**
     * Approve or reject a manual bank transfer deposit.
     */
    public function verifyBankTransfer(Request $request, $id)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'action' => 'required|string|in:approve,reject',
            'rejection_note' => 'required_if:action,reject|nullable|string|max:255',
        ]);

        $submittal = BankPaymentSubmittal::findOrFail($id);
        $admin = $request->user('sanctum');

        $this->paymentService->verifyBankTransfer(
            $submittal,
            $validated['action'],
            $validated['rejection_note'] ?? null,
            $admin
        );

        return response()->json([
            'message' => 'Bank submittal status updated successfully.',
            'data' => [
                'id' => $submittal->id,
                'status' => $submittal->fresh()->status,
            ],
        ], 200);
    }

    /**
     * Refund an order payment (supports user wallet credit returns).
     */
    public function refund(Request $request, $orderId)
    {
        Gate::authorize('manage-products');

        $validated = $request->validate([
            'note' => 'nullable|string|max:255',
        ]);

        $order = Order::findOrFail($orderId);
        $admin = $request->user('sanctum');

        $this->paymentService->refund($order, $validated['note'] ?? null, $admin);

        return response()->json([
            'message' => 'Order payment has been refunded successfully.',
        ], 200);
    }
}
