<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'segment',
    'total_spent',
    'orders_count',
    'returns_count',
    'last_ordered_at',
])]
class CustomerSegment extends Model
{
    protected function casts(): array
    {
        return [
            'total_spent' => 'decimal:2',
            'orders_count' => 'integer',
            'returns_count' => 'integer',
            'last_ordered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
