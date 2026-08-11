<?php

use App\Models\ActivityLog;
use App\Models\DeviceFingerprint;
use App\Models\User;
use App\Services\Security\DeviceTrackingService;
use App\Services\Security\RecaptchaService;
use App\Services\Security\TwoFactorAuthService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');
});

test('encrypted 2fa storage and single-use recovery code pruning', function () {
    $twoFactorService = app(TwoFactorAuthService::class);

    // 1. Enable 2FA
    $data = $twoFactorService->enable2FA($this->admin);
    expect($data['secret'])->toBeString();
    expect($data['recovery_codes'])->toHaveCount(8);

    // 2. Database Encryption Check: Raw DB query must NOT return plaintext secret!
    $rawUser = DB::table('users')->where('id', $this->admin->id)->first();
    expect($rawUser->two_factor_secret)->not()->toBe($data['secret']);
    expect(strlen($rawUser->two_factor_secret))->toBeGreaterThan(30);

    // 3. Confirm 2FA using real Google2FA OTP
    $google2fa = new Google2FA;
    $otp = $google2fa->getCurrentOtp($data['secret']);
    $confirmed = $twoFactorService->confirm2FA($this->admin, $otp);
    expect($confirmed)->toBeTrue();
    expect($this->admin->fresh()->two_factor_confirmed_at)->not()->toBeNull();

    // 4. Verify & Prune Single-Use Recovery Code
    $recoveryCode = $data['recovery_codes'][0];
    $verified = $twoFactorService->verifyCode($this->admin->fresh(), $recoveryCode);
    expect($verified)->toBeTrue();

    // Matched code must be pruned from remaining recovery codes!
    $remainingCodes = $this->admin->fresh()->two_factor_recovery_codes;
    expect($remainingCodes)->toHaveCount(7);
    expect($remainingCodes)->not()->toContain($recoveryCode);
});

test('fail-open recaptcha v3 verification evaluation', function () {
    $recaptchaService = app(RecaptchaService::class);

    $isValid = $recaptchaService->verify('valid_recaptcha_token');
    expect($isValid)->toBeTrue();
});

test('device fingerprint trust logic and audit alert logging', function () {
    $deviceService = app(DeviceTrackingService::class);

    // 1. First time login from unrecognized device -> is_trusted = false
    $device1 = $deviceService->trackDevice($this->admin, 'hash_abc123', '192.168.1.1', 'Mozilla/5.0');
    expect($device1->is_trusted)->toBeFalse();
    expect(DeviceFingerprint::count())->toBe(1);
    expect(ActivityLog::where('action', 'device.new_login_flagged')->count())->toBe(1);

    // 2. Subsequent login from same device fingerprint -> updates last_seen_at without creating duplicates
    $device2 = $deviceService->trackDevice($this->admin, 'hash_abc123', '192.168.1.1', 'Mozilla/5.0');
    expect(DeviceFingerprint::count())->toBe(1);
    expect($device2->id)->toBe($device1->id);
});

test('pci dss compliance check for payment transaction data', function () {
    $columns = DB::getSchemaBuilder()->getColumnListing('payment_transactions');

    // PCI-DSS Guarantee: No raw credit card number or CVV fields in database schema
    expect($columns)->not()->toContain('card_number');
    expect($columns)->not()->toContain('cvv');
    expect($columns)->not()->toContain('card_cvv');
    expect($columns)->not()->toContain('expiration');
});
