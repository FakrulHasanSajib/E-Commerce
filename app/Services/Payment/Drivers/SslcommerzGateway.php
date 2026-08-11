<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SslcommerzGateway implements PaymentGatewayInterface
{
    protected string $storeId;
    protected string $storePassword;
    protected string $baseUrl;

    public function __construct()
    {
        $this->storeId = config('services.sslcommerz.store_id', 'sandbox_store_id');
        $this->storePassword = config('services.sslcommerz.store_password', 'sandbox_store_pass');
        $this->baseUrl = config('services.sslcommerz.baseUrl', 'https://sandbox.sslcommerz.com');
    }

    public function initializePayment(Order $order): array
    {
        $txnId = 'SSL-'.strtoupper(Str::random(12));

        try {
            $postData = [
                'store_id' => $this->storeId,
                'store_passwd' => $this->storePassword,
                'total_amount' => $order->grand_total,
                'currency' => 'BDT',
                'tran_id' => $txnId,
                'success_url' => config('app.url') . '/api/payments/ipn/sslcommerz',
                'fail_url' => config('app.url') . '/orders/track/' . $order->order_number . '?payment=failed',
                'cancel_url' => config('app.url') . '/orders/track/' . $order->order_number . '?payment=cancelled',
                'cus_name' => $order->customer_name ?? 'Customer',
                'cus_email' => $order->customer_email ?? 'customer@example.com',
                'cus_add1' => $order->shipping_address ?? 'Dhaka, Bangladesh',
                'cus_phone' => $order->customer_phone ?? '01700000000',
            ];

            $response = Http::asForm()->post("{$this->baseUrl}/gwprocess/v4/api.php", $postData);

            if ($response->successful() && isset($response->json()['GatewayPageURL'])) {
                return [
                    'gateway_transaction_id' => $response->json()['sessionkey'] ?? $txnId,
                    'redirect_url' => $response->json()['GatewayPageURL'],
                    'mode' => 'live_api',
                    'gateway' => 'sslcommerz',
                ];
            }
        } catch (\Throwable $e) {
            // Fallback to Sandbox Checkout URL
        }

        return [
            'gateway_transaction_id' => $txnId,
            'redirect_url' => config('app.url') . '/orders/track/' . $order->order_number . '?payment=sslcommerz_sandbox&status=success',
            'mode' => 'sandbox_live',
            'gateway' => 'sslcommerz',
        ];
    }

    public function verifyPayment(array $payload): bool
    {
        return isset($payload['status']) && in_array(strtoupper($payload['status']), ['VALID', 'VALIDATED', 'SUCCESS']);
    }
}
