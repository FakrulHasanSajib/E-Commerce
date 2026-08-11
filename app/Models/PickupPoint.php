<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'contact_name',
    'contact_phone',
    'address',
    'division',
    'district',
    'upazila',
    'is_active',
])]
class PickupPoint extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get orders linked to this pickup point.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
