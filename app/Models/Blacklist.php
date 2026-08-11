<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'type',
    'value',
    'reason',
    'added_by_id',
])]
class Blacklist extends Model
{
    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('blacklists:all');
        });

        static::deleted(function () {
            Cache::forget('blacklists:all');
        });
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }
}
