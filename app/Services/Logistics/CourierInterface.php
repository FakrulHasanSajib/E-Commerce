<?php

namespace App\Services\Logistics;

use App\Models\Order;
use App\Models\PickupPoint;

interface CourierInterface
{
    /**
     * Create shipment and return gateway tracking information.
     */
    public function createShipment(Order $order, PickupPoint $pickupPoint): array;

    /**
     * Cancel an active shipment.
     */
    public function cancelShipment(string $trackingCode): bool;

    /**
     * Create a reverse pickup shipment request for returned items.
     */
    public function createReversePickup(Order $order, PickupPoint $pickupPoint): array;
}
