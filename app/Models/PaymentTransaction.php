<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'transaction_number',
    'gateway_transaction_id',
    'gateway',
    'amount',
    'status',
    'payload',
])]
class PaymentTransaction extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'json',
        ];
    }

    /**
     * Get parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
