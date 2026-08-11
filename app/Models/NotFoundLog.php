<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'url',
    'user_agent',
    'ip_address',
    'hits_count',
    'last_occurred_at',
])]
class NotFoundLog extends Model
{
    protected function casts(): array
    {
        return [
            'hits_count' => 'integer',
            'last_occurred_at' => 'datetime',
        ];
    }
}
