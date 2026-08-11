<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'label',
    'contact_name',
    'contact_phone',
    'address',
    'division',
    'district',
    'upazila',
    'country_code',
    'is_default',
])]
class UserAddress extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Register model boot lifecycle events.
     */
    protected static function booted()
    {
        static::saving(function ($address) {
            if ($address->is_default) {
                static::where('user_id', $address->user_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Get user linked to address.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
