<?php

namespace App\Services\Marketing;

use App\Models\Affiliate;
use App\Models\AffiliateReferral;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    /**
     * Register a user as an affiliate.
     */
    public function registerAffiliate(User $user, float $commissionRate = 5.00): Affiliate
    {
        $code = 'AFF-'.strtoupper(Str::random(6));

        return Affiliate::create([
            'user_id' => $user->id,
            'affiliate_code' => $code,
            'commission_rate' => $commissionRate,
            'earnings_balance' => 0.00,
            'status' => 'approved',
        ]);
    }

    /**
     * Record an order referral (with self-referral block and subtotal-based commission).
     */
    public function recordOrderReferral(Order $order, string $affiliateCode): ?AffiliateReferral
    {
        $affiliate = Affiliate::where('affiliate_code', strtoupper($affiliateCode))
            ->where('status', 'approved')
            ->first();

        if (! $affiliate) {
            return null;
        }

        // Self-Referral Abuse Block
        if ($affiliate->user_id === $order->user_id) {
            return null;
        }

        // Subtotal minus discount commission calculation
        $discount = $order->discount ?? $order->coupon_discount ?? $order->discount_amount ?? 0;
        $baseTotal = max(0, ($order->subtotal ?? $order->grand_total) - $discount);
        $commissionAmount = round($baseTotal * ($affiliate->commission_rate / 100), 2);

        return AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'order_id' => $order->id,
            'order_total' => $baseTotal,
            'commission_amount' => $commissionAmount,
            'status' => 'pending',
        ]);
    }

    /**
     * Idempotently credit affiliate earnings when order status reaches delivered.
     */
    public function creditCommissionOnDelivery(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $referral = AffiliateReferral::where('order_id', $order->id)
                ->lockForUpdate()
                ->first();

            // Idempotency Guard: credit ONLY if pending
            if (! $referral || $referral->status !== 'pending') {
                return;
            }

            $affiliate = Affiliate::where('id', $referral->affiliate_id)->lockForUpdate()->first();

            if ($affiliate) {
                $referral->update(['status' => 'paid']);
                $affiliate->increment('earnings_balance', $referral->commission_amount);
            }
        });
    }

    /**
     * Reverse credited affiliate commission on order cancellation/returns.
     */
    public function reverseCommission(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $referral = AffiliateReferral::where('order_id', $order->id)
                ->where('status', 'paid')
                ->lockForUpdate()
                ->first();

            if ($referral) {
                $affiliate = Affiliate::where('id', $referral->affiliate_id)->lockForUpdate()->first();

                if ($affiliate) {
                    $affiliate->decrement('earnings_balance', min((float) $affiliate->earnings_balance, (float) $referral->commission_amount));
                    $referral->update(['status' => 'rejected']);
                }
            }
        });
    }
}
