<?php

namespace App\Services\Return;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnRequestItem;
use App\Models\User;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnService
{
    /**
     * Customer submits a return request for a delivered order.
     */
    public function createReturnRequest(User $user, int $orderId, string $reason, string $refundMethod, array $items): ReturnRequest
    {
        $order = Order::where('user_id', $user->id)->findOrFail($orderId);

        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'Only delivered orders can be requested for return.',
            ]);
        }

        return DB::transaction(function () use ($user, $order, $reason, $refundMethod, $items) {
            $totalRefundAmount = 0.00;

            foreach ($items as $item) {
                $orderItem = $order->items()->where('id', $item['order_item_id'])->firstOrFail();
                $quantity = (int) $item['quantity'];

                if ($quantity > $orderItem->quantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity cannot exceed purchased quantity.',
                    ]);
                }

                $totalRefundAmount += ($orderItem->price * $quantity);
            }

            $returnRequest = ReturnRequest::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'status' => 'requested',
                'refund_method' => $refundMethod,
                'refund_amount' => $totalRefundAmount,
            ]);

            foreach ($items as $item) {
                ReturnRequestItem::create([
                    'return_request_id' => $returnRequest->id,
                    'order_item_id' => $item['order_item_id'],
                    'quantity' => $item['quantity'],
                    'condition' => $item['condition'] ?? 'unopened',
                ]);
            }

            return $returnRequest;
        });
    }

    /**
     * Admin processes QC inspection with idempotency guards and wallet refunds.
     */
    public function processQcStatus(int $returnRequestId, string $qcStatus, ?string $qcNotes = null, ?string $adminNotes = null): ReturnRequest
    {
        if (! in_array($qcStatus, ['qc_passed', 'qc_failed', 'rejected'])) {
            throw ValidationException::withMessages([
                'status' => 'Invalid QC status provided.',
            ]);
        }

        return DB::transaction(function () use ($returnRequestId, $qcStatus, $qcNotes, $adminNotes) {
            // Lock return request row for update
            $returnRequest = ReturnRequest::where('id', $returnRequestId)->lockForUpdate()->firstOrFail();

            // Idempotency Guard: Prevent double processing if already passed or refunded
            if (in_array($returnRequest->status, ['qc_passed', 'refunded'])) {
                throw ValidationException::withMessages([
                    'status' => 'This return request has already been QC passed/refunded.',
                ]);
            }

            $returnRequest->update([
                'status' => $qcStatus,
                'qc_notes' => $qcNotes,
                'admin_notes' => $adminNotes,
            ]);

            if ($qcStatus === 'qc_passed') {
                // 1. Restock items into inventory
                foreach ($returnRequest->items as $reqItem) {
                    $orderItem = $reqItem->orderItem;
                    if ($orderItem && $orderItem->product) {
                        $product = $orderItem->product;
                        if ($product->manage_stock) {
                            $product->increment('stock_quantity', $reqItem->quantity);
                        }
                    }
                }

                // 2. Process centralized refund (wallet credit, loyalty reversal, affiliate reversal, gift card refund, timeline audit log)
                $order = $returnRequest->order;
                if ($order) {
                    $paymentService = resolve(PaymentService::class);
                    $paymentService->refund(
                        $order,
                        "Return Request #{$returnRequest->id} Approved",
                        null,
                        'returned',
                        (float) $returnRequest->refund_amount,
                        $returnRequest->refund_method ?? 'wallet'
                    );
                }

                $returnRequest->update(['status' => 'refunded']);
            }

            return $returnRequest;
        });
    }
}
