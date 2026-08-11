<?php

namespace App\Services\Payment;

use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Initialize payment checkout session, returning transaction token and redirect URL.
     */
    public function initializePayment(Order $order): array;

    /**
     * Verify payment IPN payload authenticity and return status.
     */
    public function verifyPayment(array $payload): bool;
}
