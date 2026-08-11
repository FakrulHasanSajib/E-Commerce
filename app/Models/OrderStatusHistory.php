<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    /**
     * Disable standard updated_at since it's a log.
     */
    const UPDATED_AT = null;

    /**
     * Fillable array.
     */
    protected $fillable = [
        'order_id',
        'status',
        'note',
        'changed_by_user_id',
    ];

    /**
     * Get parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get user who changed the status.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
