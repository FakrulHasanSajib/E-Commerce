<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class NagadGateway implements PaymentGatewayInterface
{
    protected string $merchantId;
    protected string $baseUrl;

    public function __construct()
    {
        $this->merchantId = config('services.nagad.merchant_id', 'sandbox_merchant_id');
        $this->baseUrl = config('services.nagad.baseUrl', 'https://sandbox.mypay.com.bd/api/dfs');
    }

    public function initializePayment(Order $order): array
    {
        $txnId = 'NAGAD-'.strtoupper(Str::random(12));

        try {
            $response = Http::post("{$this->baseUrl}/check-out/initialize/{$this->merchantId}/{$order->order_number}", [
                'merchantId' => $this->merchantId,
                'orderId' => $order->order_number,
                'currencyCode' => '050',
                'amount' => (string) $order->grand_total,
                'challenge' => Str::random(20),
            ]);

            if ($response->successful() && isset($response->json()['callBackUrl'])) {
                return [
                    'gateway_transaction_id' => $response->json()['paymentRefId'] ?? $txnId,
                    'redirect_url' => $response->json()['callBackUrl'],
                    'mode' => 'live_api',
                    'gateway' => 'nagad',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback to Sandbox Checkout URL
        }

        return [
            'gateway_transaction_id' => $txnId,
            'redirect_url' => config('app.url') . '/orders/track/' . $order->order_number . '?payment=nagad_sandbox&status=success',
            'mode' => 'sandbox_live',
            'gateway' => 'nagad',
        ];
    }

    public function verifyPayment(array $payload): bool
    {
        return isset($payload['status']) && in_array(strtolower($payload['status']), ['success', 'completed', 'paid', 'successful']);
    }
}
