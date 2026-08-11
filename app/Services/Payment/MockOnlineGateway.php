<?php

namespace App\Services\Payment;

use App\Models\Order;
use Illuminate\Support\Str;

class MockOnlineGateway implements PaymentGatewayInterface
{
    protected string $secret = 'LarashopPaymentSecretKey2026';

    /**
     * Initialize mock payment and return fake gateway redirection.
     */
    public function initializePayment(Order $order): array
    {
        $gatewayTxnId = 'MOCK-TXN-'.strtoupper(Str::random(12));

        return [
            'redirect_url' => 'http://larashop.test/checkout/pay/'.$order->order_number.'?txn='.$gatewayTxnId,
            'gateway_transaction_id' => $gatewayTxnId,
        ];
    }

    /**
     * Verify payment IPN payload signature to prevent fraud.
     */
    public function verifyPayment(array $payload): bool
    {
        if (empty($payload['signature']) || empty($payload['transaction_number']) || empty($payload['amount'])) {
            return false;
        }

        // Expected signature matches hmac hash
        $expectedSignature = hash_hmac(
            'sha256',
            $payload['transaction_number'].'|'.$payload['amount'],
            $this->secret
        );

        return hash_equals($expectedSignature, $payload['signature']);
    }

    /**
     * Helper to generate a valid signature for testing/gateways.
     */
    public function generateSignature(string $transactionNumber, string $amount): string
    {
        return hash_hmac('sha256', $transactionNumber.'|'.$amount, $this->secret);
    }
}
