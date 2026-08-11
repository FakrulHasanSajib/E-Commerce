<?php

namespace App\Services\Marketing;

use App\Models\GiftCard;
use App\Models\GiftCardTransaction;
use App\Models\Order;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GiftCardService
{
    /**
     * Create a new Gift Card.
     */
    public function createGiftCard(float $initialValue, ?int $purchasedById = null, ?Carbon $expiresAt = null): GiftCard
    {
        $code = 'GC-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        return GiftCard::create([
            'code' => $code,
            'initial_value' => $initialValue,
            'current_balance' => $initialValue,
            'currency' => 'BDT',
            'expires_at' => $expiresAt,
            'is_active' => true,
            'purchased_by_id' => $purchasedById,
        ]);
    }

    /**
     * Fetch valid gift card for checkout calculation.
     */
    public function getValidGiftCard(string $code): ?GiftCard
    {
        return GiftCard::where('code', strtoupper($code))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where('current_balance', '>', 0)
            ->first();
    }

    /**
     * Redeem gift card balance atomically under lockForUpdate().
     */
    public function redeemGiftCard(string $code, float $amount, int|Order|null $order = null): float
    {
        $orderId = $order instanceof Order ? $order->id : $order;

        return DB::transaction(function () use ($code, $amount, $orderId) {
            $giftCard = GiftCard::where('code', strtoupper($code))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $giftCard) {
                throw new Exception('Invalid or inactive gift card code.');
            }

            if ($giftCard->expires_at && $giftCard->expires_at->isPast()) {
                throw new Exception('Gift card has expired.');
            }

            if ($giftCard->current_balance <= 0) {
                throw new Exception('Gift card has zero remaining balance.');
            }

            $deductAmount = min((float) $giftCard->current_balance, $amount);

            $giftCard->decrement('current_balance', $deductAmount);

            GiftCardTransaction::create([
                'gift_card_id' => $giftCard->id,
                'order_id' => $orderId,
                'amount' => $deductAmount,
                'type' => 'debit',
            ]);

            return $deductAmount;
        });
    }

    /**
     * Refund debited gift card amounts back on order cancellation/returns.
     */
    public function refundToGiftCard(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $debitTxs = GiftCardTransaction::where('order_id', $order->id)
                ->where('type', 'debit')
                ->get();

            foreach ($debitTxs as $tx) {
                $giftCard = GiftCard::where('id', $tx->gift_card_id)->lockForUpdate()->first();

                if ($giftCard) {
                    $giftCard->increment('current_balance', $tx->amount);

                    GiftCardTransaction::create([
                        'gift_card_id' => $giftCard->id,
                        'order_id' => $order->id,
                        'amount' => $tx->amount,
                        'type' => 'credit',
                    ]);
                }
            }
        });
    }
}
