<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'user_id',
    'subject_type',
    'subject_id',
    'action',
    'description',
    'properties',
    'ip_address',
    'user_agent',
])]
class ActivityLog extends Model
{
    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    protected static function booted()
    {
        // Audit Immutability Guard: prohibit updates or deletions of audit logs
        static::updating(function () {
            throw new Exception('Activity logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new Exception('Activity logs are immutable and cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
