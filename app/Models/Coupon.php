<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'code',
    'type',
    'value',
    'min_purchase_amount',
    'starts_at',
    'ends_at',
    'max_uses',
    'max_uses_per_user',
    'uses_count',
    'is_active',
])]
class Coupon extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_purchase_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'uses_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The users that have used this coupon.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot('used_at');
    }

    /**
     * Determine if the coupon is valid for a given user and cart subtotal.
     */
    public function isValidFor(?User $user, float $subtotal): bool
    {
        if (! $this->is_active) {
            throw new \Exception('This coupon code is inactive.');
        }

        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) {
            throw new \Exception('This coupon code is not active yet.');
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            throw new \Exception('This coupon code has expired.');
        }

        if ($subtotal < (float) $this->min_purchase_amount) {
            throw new \Exception('A minimum purchase of $'.number_format($this->min_purchase_amount, 2).' is required to use this coupon.');
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            throw new \Exception('This coupon code has reached its maximum usage limit.');
        }

        if ($this->max_uses_per_user !== null && $user) {
            $userUsage = DB::table('coupon_user')
                ->where('coupon_id', $this->id)
                ->where('user_id', $user->id)
                ->count();

            if ($userUsage >= $this->max_uses_per_user) {
                throw new \Exception('You have reached the usage limit for this coupon code.');
            }
        }

        return true;
    }

    /**
     * Calculate discount amount.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percentage') {
            return round(($subtotal * ((float) $this->value / 100)), 2);
        }

        if ($this->type === 'fixed') {
            return min(round((float) $this->value, 2), $subtotal);
        }

        // For free_shipping type, value is 0.00 and coupon discount doesn't affect item subtotal directly
        return 0.00;
    }
}
