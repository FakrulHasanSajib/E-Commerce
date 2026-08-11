<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'coupon_id',
    'token',
    'converted_to_order_id',
])]
class Cart extends Model
{
    /**
     * Get the user that owns the cart.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the applied coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get the order this cart was converted to.
     */
    public function convertedToOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_to_order_id');
    }

    /**
     * Get the items in the cart.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Calculate subtotal of cart items.
     */
    public function getSubtotal(): float
    {
        return (float) $this->items->sum(function ($item) {
            return $item->getItemPrice() * $item->quantity;
        });
    }

    /**
     * Calculate discount benefits.
     */
    public function getDiscount(): float
    {
        if (! $this->coupon_id || ! $this->coupon) {
            return 0.00;
        }

        try {
            // Validate to ensure it's still applicable (e.g. minimum spend check)
            $this->coupon->isValidFor($this->user, $this->getSubtotal());

            return $this->coupon->calculateDiscount($this->getSubtotal());
        } catch (\Throwable $e) {
            // If the coupon becomes invalid, automatically detach it
            $this->coupon_id = null;
            $this->save();

            return 0.00;
        }
    }

    /**
     * Get the final total amount.
     */
    public function getTotal(): float
    {
        return max(0.00, round($this->getSubtotal() - $this->getDiscount(), 2));
    }
}
