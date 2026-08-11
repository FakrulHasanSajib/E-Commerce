<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\Marketing\AffiliateService;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    public function __construct(protected AffiliateService $affiliateService) {}

    /**
     * View authenticated customer's affiliate details and earnings.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        $affiliate = Affiliate::where('user_id', $user->id)
            ->with(['referrals.order'])
            ->first();

        if (! $affiliate) {
            return response()->json([
                'is_affiliate' => false,
                'data' => null,
            ]);
        }

        return response()->json([
            'is_affiliate' => true,
            'data' => [
                'affiliate_code' => $affiliate->affiliate_code,
                'commission_rate' => (float) $affiliate->commission_rate,
                'earnings_balance' => (float) $affiliate->earnings_balance,
                'referral_link' => url('/?ref='.$affiliate->affiliate_code),
                'referrals' => $affiliate->referrals,
            ],
        ]);
    }

    /**
     * Register customer as an affiliate.
     */
    public function register(Request $request)
    {
        $user = $request->user();

        if (Affiliate::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'You are already registered as an affiliate.'], 422);
        }

        $affiliate = $this->affiliateService->registerAffiliate($user);

        return response()->json([
            'message' => 'Affiliate account created successfully.',
            'data' => $affiliate,
        ], 201);
    }
}
