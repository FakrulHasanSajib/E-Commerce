<?php

namespace App\Services\Security;

use App\Models\DeviceFingerprint;
use App\Models\User;
use App\Services\Admin\AuditLogService;
use Illuminate\Support\Carbon;

class DeviceTrackingService
{
    public function __construct(protected AuditLogService $auditLogService) {}

    /**
     * Track device fingerprint (new devices default to is_trusted = false and trigger audit alerts).
     */
    public function trackDevice(User $user, string $fingerprintHash, string $ipAddress, ?string $userAgent = null): DeviceFingerprint
    {
        $device = DeviceFingerprint::where('user_id', $user->id)
            ->where('fingerprint_hash', $fingerprintHash)
            ->first();

        if (! $device) {
            $device = DeviceFingerprint::create([
                'user_id' => $user->id,
                'fingerprint_hash' => $fingerprintHash,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
                'last_seen_at' => Carbon::now(),
                'is_trusted' => false, // Default untrusted for new device logins!
            ]);

            $this->auditLogService->log(
                'device.new_login_flagged',
                $device,
                ['ip' => $ipAddress, 'fingerprint' => $fingerprintHash],
                "New unrecognized device login for user {$user->email} from IP {$ipAddress}."
            );
        } else {
            $device->update([
                'ip_address' => $ipAddress,
                'last_seen_at' => Carbon::now(),
            ]);
        }

        return $device;
    }
}
