<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthService
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate 2FA RFC 6238 TOTP secret, QR URI, and 8 single-use recovery codes.
     */
    public function enable2FA(User $user): array
    {
        $secret = $this->google2fa->generateSecretKey();
        $recoveryCodes = [];
        for ($i = 0; $i < 8; $i++) {
            $recoveryCodes[] = Str::random(5).'-'.Str::random(5);
        }

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        $appName = config('app.name', 'LaraShop');
        $email = $user->email;
        $qrUri = $this->google2fa->getQRCodeUrl($appName, $email, $secret);

        return [
            'secret' => $secret,
            'qr_uri' => $qrUri,
            'recovery_codes' => $recoveryCodes,
        ];
    }

    /**
     * Confirm 2FA activation with a 6-digit TOTP code.
     */
    public function confirm2FA(User $user, string $code): bool
    {
        if ($this->validateTotp($user->two_factor_secret, $code)) {
            $user->update([
                'two_factor_confirmed_at' => Carbon::now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable2FA(User $user): void
    {
        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    /**
     * Verify TOTP code or single-use recovery code (pruning used recovery code from encrypted array).
     */
    public function verifyCode(User $user, string $code): bool
    {
        // 1. Validate 6-digit RFC 6238 TOTP code
        if ($this->validateTotp($user->two_factor_secret, $code)) {
            return true;
        }

        // 2. Validate & Prune Single-Use Recovery Code
        $codes = $user->two_factor_recovery_codes ?? [];
        if (is_array($codes) && in_array($code, $codes)) {
            $remaining = array_values(array_diff($codes, [$code]));
            $user->update([
                'two_factor_recovery_codes' => $remaining,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Validate RFC 6238 TOTP secret against code using Pragmarx Google2FA.
     */
    public function validateTotp(?string $secret, string $code): bool
    {
        if (! $secret || strlen($code) !== 6 || ! ctype_digit($code)) {
            return false;
        }

        try {
            // Allows 1 window (30 seconds) clock drift forward or backward
            return (bool) $this->google2fa->verifyKey($secret, $code, 1);
        } catch (\Exception $e) {
            return false;
        }
    }
}
