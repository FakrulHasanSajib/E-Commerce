<?php

namespace App\Services\Logistics;

use App\Models\Order;
use App\Models\PickupPoint;
use Illuminate\Support\Str;

class MockCourierDriver implements CourierInterface
{
    protected string $secret = 'LarashopCourierSecretKey2026';

    /**
     * Creates a mock shipment and returns tracking references.
     */
    public function createShipment(Order $order, PickupPoint $pickupPoint): array
    {
        $trackingCode = 'TRACK-STEADFAST-'.strtoupper(Str::random(12));

        return [
            'tracking_code' => $trackingCode,
            'status' => 'pending',
        ];
    }

    /**
     * Cancels mock shipment.
     */
    public function cancelShipment(string $trackingCode): bool
    {
        return true;
    }

    /**
     * Creates mock reverse pickup.
     */
    public function createReversePickup(Order $order, PickupPoint $pickupPoint): array
    {
        $returnCode = 'RET-STEADFAST-'.strtoupper(Str::random(12));

        return [
            'tracking_code' => $returnCode,
            'status' => 'pending',
        ];
    }

    /**
     * Helper to verify webhook signature.
     */
    public function verifyWebhookSignature(string $trackingCode, string $status, string $signature): bool
    {
        $expectedSignature = hash_hmac('sha256', $trackingCode.'|'.$status, $this->secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Helper to generate webhook signature.
     */
    public function generateWebhookSignature(string $trackingCode, string $status): string
    {
        return hash_hmac('sha256', $trackingCode.'|'.$status, $this->secret);
    }
}
