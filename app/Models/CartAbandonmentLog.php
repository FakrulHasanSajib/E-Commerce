<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'email',
    'cart_data',
    'last_activity_at',
    'notification_sent_at',
    'is_recovered',
])]
class CartAbandonmentLog extends Model
{
    protected function casts(): array
    {
        return [
            'cart_data' => 'array',
            'last_activity_at' => 'datetime',
            'notification_sent_at' => 'datetime',
            'is_recovered' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
