<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'type',
    'content',
    'is_active',
    'delay_seconds',
])]
class Popup extends Model
{
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
            'delay_seconds' => 'integer',
        ];
    }
}
