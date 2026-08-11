<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'bank_name',
    'account_number',
    'transaction_reference',
    'amount',
    'receipt_media_id',
    'status',
    'rejection_note',
    'verified_by_user_id',
])]
class BankPaymentSubmittal extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Get parent order.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get uploaded receipt image.
     */
    public function receiptMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'receipt_media_id');
    }

    /**
     * Get staff uploader that verified.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }
}
