<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use Illuminate\Http\Request;

class GiftCardController extends Controller
{
    /**
     * Validate and apply a gift card code to check balance (Throttled 10/min).
     */
    public function apply(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:32',
        ]);

        $giftCard = GiftCard::where('code', strtoupper($validated['code']))
            ->where('is_active', true)
            ->first();

        if (! $giftCard) {
            return response()->json(['message' => 'Invalid or inactive gift card code.'], 422);
        }

        if ($giftCard->expires_at && $giftCard->expires_at->isPast()) {
            return response()->json(['message' => 'Gift card has expired.'], 422);
        }

        if ($giftCard->current_balance <= 0) {
            return response()->json(['message' => 'Gift card has zero remaining balance.'], 422);
        }

        return response()->json([
            'data' => [
                'code' => $giftCard->code,
                'current_balance' => (float) $giftCard->current_balance,
                'currency' => $giftCard->currency,
            ],
        ]);
    }
}
