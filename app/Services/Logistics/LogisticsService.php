<?php

namespace App\Services\Logistics;

use App\Models\Order;
use App\Models\PickupPoint;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LogisticsService
{
    /**
     * Resolves the courier driver instance.
     */
    protected function getCourierDriver(string $courierName): CourierInterface
    {
        if (in_array(strtolower($courierName), ['steadfast', 'pathao', 'paperfly', 'redx'])) {
            return new MockCourierDriver;
        }

        throw new \InvalidArgumentException("Logistics driver [{$courierName}] not supported.");
    }

    /**
     * Dispatches order via courier, updating order with tracking details and shipped status.
     */
    public function dispatchOrder(Order $order, int $pickupPointId, string $courierName): void
    {
        // Enforce valid dispatch state (must be confirmed or processing)
        if (! in_array($order->status, ['confirmed', 'processing'])) {
            throw ValidationException::withMessages([
                'order' => 'Order must be confirmed or processing to be dispatched.',
            ]);
        }

        $pickupPoint = PickupPoint::findOrFail($pickupPointId);
        $driver = $this->getCourierDriver($courierName);

        $result = $driver->createShipment($order, $pickupPoint);

        DB::transaction(function () use ($order, $pickupPointId, $courierName, $result) {
            $order->courier_name = $courierName;
            $order->courier_tracking_code = $result['tracking_code'];
            $order->pickup_point_id = $pickupPointId;
            $order->save();

            // Transition to processing
            $orderService = resolve(OrderService::class);
            $orderService->updateStatus(
                $order,
                'processing',
                "Order dispatched via [{$courierName}]. Tracking code: {$result['tracking_code']}."
            );
        });
    }

    /**
     * Process incoming webhook callbacks from courier integrations.
     */
    public function processCourierWebhook(string $courierName, array $payload): void
    {
        $driver = $this->getCourierDriver($courierName);

        // Security check signature
        if (! $driver->verifyWebhookSignature($payload['tracking_code'], $payload['status'], $payload['signature'])) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid courier webhook signature.',
            ]);
        }

        // Lock order lookup
        $order = Order::where('courier_tracking_code', $payload['tracking_code'])
            ->lockForUpdate()
            ->first();

        if (! $order) {
            return; // Silently exit to stop webhook retries
        }

        DB::transaction(function () use ($order, $payload) {
            $orderService = resolve(OrderService::class);

            if ($payload['status'] === 'in_transit') {
                $orderService->updateStatus($order, 'shipped', 'Courier update: shipment is in transit.');
            } elseif ($payload['status'] === 'delivered') {
                // If COD order, mark payment as paid
                if ($order->payment_method === 'cod') {
                    $order->payment_status = 'paid';
                    $order->save();
                }

                $orderService->updateStatus($order, 'delivered', 'Courier update: shipment delivered.');
            } elseif ($payload['status'] === 'returned') {
                // Returns trigger refunding/wallet crediting back
                $paymentService = resolve(PaymentService::class);
                $paymentService->refund($order, 'Automated refund triggered by returned courier webhook.', null, 'returned');
            }
        });
    }

    /**
     * Initiates reverse pickup for returns/exchanges.
     */
    public function requestReversePickup(Order $order, int $pickupPointId): void
    {
        if ($order->status !== 'delivered') {
            throw ValidationException::withMessages([
                'order' => 'Order must be delivered to request a return pickup.',
            ]);
        }

        $pickupPoint = PickupPoint::findOrFail($pickupPointId);
        $driver = $this->getCourierDriver($order->courier_name ?: 'steadfast');

        $result = $driver->createReversePickup($order, $pickupPoint);

        DB::transaction(function () use ($order, $result) {
            $orderService = resolve(OrderService::class);
            $orderService->updateStatus(
                $order,
                'returned',
                "Reverse pickup scheduled with tracking: {$result['tracking_code']}."
            );
        });
    }
}
