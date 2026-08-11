<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'source_url',
    'target_url',
    'status_code',
    'hits_count',
    'is_active',
])]
class UrlRedirect extends Model
{
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted()
    {
        static::saved(function () {
            Cache::forget('redirects:all');
        });

        static::deleted(function () {
            Cache::forget('redirects:all');
        });
    }
}
