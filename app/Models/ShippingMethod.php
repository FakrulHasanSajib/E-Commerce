<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'shipping_zone_id',
    'name',
    'type',
    'cost',
    'min_order_amount',
    'is_active',
    'default_courier',
])]
class ShippingMethod extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get parent shipping zone.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    /**
     * Get weight-based rates for this method.
     */
    public function weightRates(): HasMany
    {
        return $this->hasMany(ShippingWeightRate::class)->orderBy('max_weight', 'asc');
    }

    /**
     * Calculate cost based on cart subtotal and total weight.
     */
    public function calculateCost(float $subtotal, float $totalWeight): float
    {
        if ($this->type === 'free_shipping') {
            if ($subtotal >= (float) $this->min_order_amount) {
                return 0.00;
            }

            // If subtotal is below minimum, return base cost (or handle as unavailable - let's return base cost)
            return (float) $this->cost;
        }

        if ($this->type === 'flat_rate') {
            return (float) $this->cost;
        }

        if ($this->type === 'weight_based') {
            $rates = $this->weightRates;

            if ($rates->isEmpty()) {
                return (float) $this->cost;
            }

            foreach ($rates as $rate) {
                if ($totalWeight <= (float) $rate->max_weight) {
                    return (float) $rate->cost;
                }
            }

            // If weight exceeds the highest bracket, throw exception to prevent undercharging
            throw new \Exception('Cart weight exceeds the maximum shipping weight limit for '.$this->name.'.');
        }

        return (float) $this->cost;
    }
}
