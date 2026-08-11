<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Security\TwoFactorAuthService;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(protected TwoFactorAuthService $twoFactorAuthService) {}

    /**
     * Enable 2FA for authenticated admin.
     */
    public function enable(Request $request)
    {
        $user = $request->user();

        $data = $this->twoFactorAuthService->enable2FA($user);
        
        $encodedUri = urlencode($data['qr_uri']);
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$encodedUri}";
        $qrSvg = "<img src=\"{$qrUrl}\" alt=\"2FA QR Code\" class=\"w-44 h-44 object-contain rounded-xl shadow-md\" />";

        return response()->json([
            'message' => '2FA initialized. Please scan QR code and confirm 6-digit code.',
            'secret' => $data['secret'],
            'qr_uri' => $data['qr_uri'],
            'qr_url' => $qrUrl,
            'svg' => $qrSvg,
            'recovery_codes' => $data['recovery_codes'],
            'data' => $data,
        ]);
    }

    /**
     * Confirm 2FA activation with a 6-digit TOTP code.
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        $confirmed = $this->twoFactorAuthService->confirm2FA($user, $validated['code']);

        if (! $confirmed) {
            return response()->json(['message' => 'Invalid 2FA authentication code.'], 422);
        }

        return response()->json([
            'message' => 'Two-factor authentication confirmed and activated successfully.',
            'recovery_codes' => $user->two_factor_recovery_codes ?? [],
        ]);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request)
    {
        $user = $request->user();
        $this->twoFactorAuthService->disable2FA($user);

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }

    /**
     * Challenge 2FA code during login session.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user();

        $valid = $this->twoFactorAuthService->verifyCode($user, $validated['code']);

        if (! $valid) {
            return response()->json(['message' => 'Invalid two-factor authentication code or recovery code.'], 422);
        }

        $request->session()->put('2fa_verified', true);

        return response()->json([
            'message' => 'Two-factor authentication verified successfully.',
        ]);
    }
}
