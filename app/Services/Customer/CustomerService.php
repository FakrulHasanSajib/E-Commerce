<?php

namespace App\Services\Customer;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Credit loyalty points to a user based on delivered order.
     */
    public function creditLoyaltyPoints(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }

        $ratePercent = (float) setting('loyalty.earn_rate_percent', 1.0);
        $pointsEarned = (int) floor($order->grand_total * ($ratePercent / 100));

        if ($pointsEarned <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $pointsEarned) {
            $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();
            $user->increment('loyalty_points', $pointsEarned);

            $user->loyaltyTransactions()->create([
                'points' => $pointsEarned,
                'type' => 'earned',
                'description' => "Points earned from order {$order->order_number}.",
            ]);
        });
    }

    /**
     * Deduct earned loyalty points if the order is refunded/returned.
     */
    public function reverseLoyaltyPoints(Order $order): void
    {
        if (! $order->user_id) {
            return;
        }

        $ratePercent = (float) setting('loyalty.earn_rate_percent', 1.0);
        $pointsEarned = (int) floor($order->grand_total * ($ratePercent / 100));

        if ($pointsEarned <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $pointsEarned) {
            $user = User::where('id', $order->user_id)->lockForUpdate()->firstOrFail();
            $user->decrement('loyalty_points', $pointsEarned);

            $user->loyaltyTransactions()->create([
                'points' => -$pointsEarned,
                'type' => 'reversed',
                'description' => "Points reversed due to return/refund of order {$order->order_number}.",
            ]);
        });
    }

    /**
     * Redeem loyalty points for wallet balance.
     */
    public function redeemLoyaltyPoints(User $user, int $points): void
    {
        if ($points <= 0) {
            throw ValidationException::withMessages([
                'points' => 'Redemption points must be greater than zero.',
            ]);
        }

        DB::transaction(function () use ($user, $points) {
            $lockedUser = User::where('id', $user->id)->lockForUpdate()->firstOrFail();

            if ($lockedUser->loyalty_points < $points) {
                throw ValidationException::withMessages([
                    'points' => 'Insufficient loyalty points.',
                ]);
            }

            $redemptionRate = (int) setting('loyalty.redemption_rate', 10);
            if ($redemptionRate <= 0) {
                $redemptionRate = 10;
            }

            $walletAmount = (float) ($points / $redemptionRate);

            // Deduct points
            $lockedUser->decrement('loyalty_points', $points);

            // Increment wallet balance
            $lockedUser->increment('wallet_balance', $walletAmount);

            // Log loyalty transaction
            $lockedUser->loyaltyTransactions()->create([
                'points' => -$points,
                'type' => 'redeemed',
                'description' => "Redeemed {$points} points for wallet credit.",
            ]);

            // Log wallet transaction
            $lockedUser->walletTransactions()->create([
                'amount' => $walletAmount,
                'type' => 'loyalty_redemption',
                'description' => "Redeemed {$points} loyalty points.",
            ]);
        });
    }

    /**
     * Process referral code reward triggers on user registration.
     */
    public function processReferral(User $newUser, string $referralCode): void
    {
        $referrer = User::where('referral_code', $referralCode)->first();

        if (! $referrer) {
            throw ValidationException::withMessages([
                'referral_code' => 'Invalid referral code.',
            ]);
        }

        // Abuse Check: Prevent self-referral
        if ($referrer->id === $newUser->id || $referrer->email === $newUser->email) {
            throw ValidationException::withMessages([
                'referral_code' => 'Self-referral is not allowed.',
            ]);
        }

        DB::transaction(function () use ($newUser, $referrer) {
            // Map relation
            $newUser->referred_by_id = $referrer->id;
            $newUser->save();

            // Lock user records
            $lockedReferrer = User::where('id', $referrer->id)->lockForUpdate()->firstOrFail();
            $lockedNewUser = User::where('id', $newUser->id)->lockForUpdate()->firstOrFail();

            $referrerReward = (float) setting('referral.referrer_reward', 50.00);
            $refereeReward = (float) setting('referral.referee_reward', 20.00);

            // Award Referrer
            if ($referrerReward > 0) {
                $lockedReferrer->increment('wallet_balance', $referrerReward);
                $lockedReferrer->walletTransactions()->create([
                    'amount' => $referrerReward,
                    'type' => 'referral_bonus',
                    'description' => "Referral bonus for referring {$newUser->name}.",
                ]);
            }

            // Award Referee
            if ($refereeReward > 0) {
                $lockedNewUser->increment('wallet_balance', $refereeReward);
                $lockedNewUser->walletTransactions()->create([
                    'amount' => $refereeReward,
                    'type' => 'referral_bonus',
                    'description' => "Bonus for registering via referral from {$referrer->name}.",
                ]);
            }
        });
    }
}
