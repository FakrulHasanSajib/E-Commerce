<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'order_number',
    'status',
    'subtotal',
    'discount',
    'shipping_cost',
    'tax_cost',
    'grand_total',
    'coupon_id',
    'shipping_method_id',
    'payment_method',
    'payment_status',
    'customer_name',
    'customer_email',
    'customer_phone',
    'shipping_address',
    'division',
    'district',
    'upazila',
    'courier_name',
    'courier_tracking_code',
    'pickup_point_id',
    'delivery_date',
    'delivery_slot_label',
    'is_flagged_fraud',
    'fraud_reasons',
])]
class Order extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'tax_cost' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'delivery_date' => 'date',
            'is_flagged_fraud' => 'boolean',
            'fraud_reasons' => 'array',
        ];
    }

    /**
     * Get parent user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get applied coupon.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Get selected shipping method.
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /**
     * Get items in the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get history records of the order.
     */
    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the pickup point assigned to this order.
     */
    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(PickupPoint::class);
    }
}
