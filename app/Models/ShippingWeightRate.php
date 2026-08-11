<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'shipping_method_id',
    'max_weight',
    'cost',
])]
class ShippingWeightRate extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'max_weight' => 'decimal:2',
            'cost' => 'decimal:2',
        ];
    }

    /**
     * Get parent shipping method.
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
}
