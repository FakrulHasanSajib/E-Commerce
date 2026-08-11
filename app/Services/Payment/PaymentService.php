<?php

namespace App\Services\Payment;

use App\Models\BankPaymentSubmittal;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Customer\CustomerService;
use App\Services\Marketing\AffiliateService;
use App\Services\Marketing\GiftCardService;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Resolves the gateway driver instance.
     */
    protected function getGatewayDriver(string $gatewayName): PaymentGatewayInterface
    {
        return match ($gatewayName) {
            'bkash' => new \App\Services\Payment\Drivers\BkashGateway,
            'nagad' => new \App\Services\Payment\Drivers\NagadGateway,
            'sslcommerz' => new \App\Services\Payment\Drivers\SslcommerzGateway,
            default => new MockOnlineGateway,
        };
    }

    /**
     * Initializes online gateway payment session.
     */
    public function initializeOnlinePayment(Order $order, string $gatewayName): array
    {
        $driver = $this->getGatewayDriver($gatewayName);
        $result = $driver->initializePayment($order);

        PaymentTransaction::create([
            'order_id' => $order->id,
            'transaction_number' => 'TXN-'.strtoupper(Str::random(12)),
            'gateway_transaction_id' => $result['gateway_transaction_id'],
            'gateway' => $gatewayName,
            'amount' => $order->grand_total,
            'status' => 'pending',
        ]);

        return [
            'redirect_url' => $result['redirect_url'],
        ];
    }

    /**
     * Process incoming gateway webhooks (IPN) with signature and idempotency checks.
     */
    public function processOnlineIPN(string $gatewayName, array $payload): bool
    {
        $driver = $this->getGatewayDriver($gatewayName);

        // 1. Signature Security Validation
        if (! $driver->verifyPayment($payload)) {
            throw ValidationException::withMessages([
                'signature' => 'Invalid payment signature key.',
            ]);
        }

        // 2. Idempotency Check: Fetch and lock transaction
        return DB::transaction(function () use ($payload, $gatewayName) {
            $transaction = PaymentTransaction::where('transaction_number', $payload['transaction_number'])
                ->lockForUpdate()
                ->firstOrFail();

            // Silently ignore if already processed successfully (IPN retry loop safety)
            if ($transaction->status === 'success') {
                return true;
            }

            // Update transaction state
            $transaction->status = $payload['status'] === 'success' ? 'success' : 'failed';
            $transaction->payload = $payload;
            $transaction->save();

            $order = $transaction->order;

            if ($transaction->status === 'success') {
                // Determine partial payment status (e.g. COD advance charge match checks)
                $order->payment_status = (float) $payload['amount'] >= (float) $order->grand_total ? 'paid' : 'partial';
                $order->save();

                // Auto-confirm order and record history log
                $orderService = resolve(OrderService::class);
                $orderService->updateStatus($order, 'confirmed', "Paid via online gateway [{$gatewayName}].");
            }

            return true;
        });
    }

    /**
     * Submits manual bank transfer details for verification.
     */
    public function submitBankTransfer(Order $order, array $submittalDetails): BankPaymentSubmittal
    {
        if ($order->payment_method !== 'bank_transfer') {
            throw ValidationException::withMessages([
                'order' => 'Order is not configured for manual bank transfer payment.',
            ]);
        }

        return BankPaymentSubmittal::create([
            'order_id' => $order->id,
            'bank_name' => $submittalDetails['bank_name'],
            'account_number' => $submittalDetails['account_number'],
            'transaction_reference' => $submittalDetails['transaction_reference'],
            'amount' => $submittalDetails['amount'],
            'receipt_media_id' => $submittalDetails['receipt_media_id'] ?? null,
            'status' => 'pending',
        ]);
    }

    /**
     * Admin workflow to approve or reject manual bank transfer requests.
     */
    public function verifyBankTransfer(
        BankPaymentSubmittal $submittal,
        string $action,
        ?string $note,
        User $admin
    ): void {
        DB::transaction(function () use ($submittal, $action, $note, $admin) {
            $order = $submittal->order;

            if ($action === 'approve') {
                $submittal->status = 'approved';
                $submittal->verified_by_user_id = $admin->id;
                $submittal->rejection_note = null;
                $submittal->save();

                // Partial payment check
                $order->payment_status = $submittal->amount >= $order->grand_total ? 'paid' : 'partial';
                $order->save();

                // Auto-confirm order
                $orderService = resolve(OrderService::class);
                $orderService->updateStatus($order, 'confirmed', "Bank payment approved by admin: {$admin->name}.");
            } else {
                $submittal->status = 'rejected';
                $submittal->verified_by_user_id = $admin->id;
                $submittal->rejection_note = $note;
                $submittal->save();

                // Log timeline status rejection update
                $orderService = resolve(OrderService::class);
                $orderService->updateStatus($order, 'pending', "Bank payment rejected by admin: {$note}.");
            }
        });
    }

    /**
     * Refunding orders, reversing payment status, and returning balances based on refund_method.
     */
    public function refund(
        Order $order,
        ?string $note = null,
        ?User $admin = null,
        string $targetStatus = 'cancelled',
        ?float $amount = null,
        string $refundMethod = 'wallet'
    ): void {
        DB::transaction(function () use ($order, $amount, $note, $admin, $targetStatus, $refundMethod) {
            $refundAmount = $amount ?? (float) $order->grand_total;

            // Partial vs full refund payment status update
            $order->payment_status = $refundAmount >= (float) $order->grand_total ? 'refunded' : 'partial';
            $order->save();

            // Log reversed payment transaction
            PaymentTransaction::create([
                'order_id' => $order->id,
                'transaction_number' => 'REF-'.strtoupper(Str::random(12)),
                'gateway' => $order->payment_method,
                'amount' => -$refundAmount,
                'status' => 'reversed',
                'payload' => [
                    'note' => $note,
                    'refunded_by' => $admin?->name,
                    'refund_method' => $refundMethod,
                    'refund_amount' => $refundAmount,
                ],
            ]);

            // Restore Wallet Balance ONLY if refundMethod choice is wallet and user exists
            if ($refundMethod === 'wallet' && $order->user) {
                $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();
                $user->increment('wallet_balance', $refundAmount);

                $user->walletTransactions()->create([
                    'amount' => $refundAmount,
                    'type' => 'credit',
                    'description' => "Refund balance for order {$order->order_number}.",
                ]);
            }

            // Reverse Loyalty Points
            $customerService = resolve(CustomerService::class);
            $customerService->reverseLoyaltyPoints($order);

            // Reverse Affiliate Commission
            $affiliateService = resolve(AffiliateService::class);
            $affiliateService->reverseCommission($order);

            // Refund Gift Card Balance if gift card was redeemed
            $giftCardService = resolve(GiftCardService::class);
            $giftCardService->refundToGiftCard($order);

            // Update order status (either cancelled or returned) and log timeline notes
            $orderService = resolve(OrderService::class);
            $orderService->updateStatus($order, $targetStatus, "Order refunded ({$refundMethod}: ৳{$refundAmount}). Note: {$note}", $admin);
        });
    }
}
