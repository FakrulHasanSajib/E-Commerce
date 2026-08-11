<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    /**
     * Initializes an online payment transaction.
     */
    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'gateway' => 'required|string|in:mock_online,bkash,nagad,sslcommerz,shurjopay,stripe,cod,bank_transfer',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        // Security check: Verify order ownership
        $user = $request->user('sanctum');
        if ($order->user_id !== null && (! $user || $order->user_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $result = $this->paymentService->initializeOnlinePayment($order, $validated['gateway']);

        return response()->json([
            'data' => $result,
        ], 200);
    }

    /**
     * Webhook Instant Payment Notification (IPN) handler.
     */
    public function ipn(Request $request, string $gateway)
    {
        try {
            $this->paymentService->processOnlineIPN($gateway, $request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Notification processed successfully.',
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Submits manual bank transfer deposit slip details.
     */
    public function submitBankTransfer(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'transaction_reference' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'receipt_media_id' => 'nullable|integer|exists:media,id',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        // Verify order ownership
        $user = $request->user('sanctum');
        if ($order->user_id !== null && (! $user || $order->user_id !== $user->id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $submittal = $this->paymentService->submitBankTransfer($order, $validated);

        return response()->json([
            'message' => 'Bank transfer details submitted successfully.',
            'data' => [
                'id' => $submittal->id,
                'status' => $submittal->status,
            ],
        ], 200);
    }
}
