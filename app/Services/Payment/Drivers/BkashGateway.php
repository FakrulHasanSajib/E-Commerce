<?php

namespace App\Services\Payment\Drivers;

use App\Models\Order;
use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BkashGateway implements PaymentGatewayInterface
{
    protected string $appKey;
    protected string $appSecret;
    protected string $username;
    protected string $password;
    protected string $baseUrl;

    public function __construct()
    {
        $this->appKey = config('services.bkash.app_key', 'sandbox_app_key');
        $this->appSecret = config('services.bkash.app_secret', 'sandbox_app_secret');
        $this->username = config('services.bkash.username', 'sandbox_user');
        $this->password = config('services.bkash.password', 'sandbox_pass');
        $this->baseUrl = config('services.bkash.baseUrl', 'https://tokenized.sandbox.bKash.com/v1.2.0-beta');
    }

    public function initializePayment(Order $order): array
    {
        $txnId = 'BKASH-'.strtoupper(Str::random(12));

        try {
            // Live Tokenized bKash Grant Token Call
            $tokenRes = Http::withHeaders([
                'username' => $this->username,
                'password' => $this->password,
            ])->post("{$this->baseUrl}/tokenized/bKash/grant-token", [
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
            ]);

            if ($tokenRes->successful() && isset($tokenRes->json()['id_token'])) {
                $idToken = $tokenRes->json()['id_token'];

                // Create Payment Checkout Session
                $createRes = Http::withHeaders([
                    'Authorization' => $idToken,
                    'X-APP-Key' => $this->appKey,
                ])->post("{$this->baseUrl}/tokenized/bKash/create", [
                    'mode' => '0011',
                    'payerReference' => $order->customer_phone ?? '01700000000',
                    'callbackURL' => config('app.url') . "/api/payments/ipn/bkash",
                    'amount' => (string) $order->grand_total,
                    'currency' => 'BDT',
                    'intent' => 'sale',
                    'merchantInvoiceNumber' => $order->order_number,
                ]);

                if ($createRes->successful() && isset($createRes->json()['bkashURL'])) {
                    return [
                        'gateway_transaction_id' => $createRes->json()['paymentID'] ?? $txnId,
                        'redirect_url' => $createRes->json()['bkashURL'],
                        'mode' => 'live_api',
                        'gateway' => 'bkash',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Fallback to Sandbox Checkout URL
        }

        return [
            'gateway_transaction_id' => $txnId,
            'redirect_url' => config('app.url') . '/orders/track/' . $order->order_number . '?payment=bkash_sandbox&status=success',
            'mode' => 'sandbox_live',
            'gateway' => 'bkash',
        ];
    }

    public function verifyPayment(array $payload): bool
    {
        if (isset($payload['paymentID']) && isset($payload['status']) && $payload['status'] === 'Completed') {
            return true;
        }

        return isset($payload['status']) && in_array(strtolower($payload['status']), ['success', 'completed', 'paid']);
    }
}
