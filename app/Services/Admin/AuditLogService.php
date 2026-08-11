<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    /**
     * Record an audit log entry with property diffs and user IP address.
     */
    public function log(string $action, ?Model $subject = null, ?array $properties = null, ?string $description = null): ActivityLog
    {
        $user = auth('sanctum')->user() ?? auth()->user();
        $request = request();

        return ActivityLog::forceCreate([
            'user_id' => $user?->id,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'action' => $action,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? substr($request->userAgent(), 0, 255) : null,
        ]);
    }
}
