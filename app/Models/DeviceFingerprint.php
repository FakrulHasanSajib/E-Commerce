<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'fingerprint_hash',
    'ip_address',
    'user_agent',
    'last_seen_at',
    'is_trusted',
])]
class DeviceFingerprint extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'is_trusted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
