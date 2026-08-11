<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'slug',
])]
class TaxClass extends Model
{
    /**
     * Get the flat rate associated with this tax class.
     */
    public function rate(): HasOne
    {
        return $this->hasOne(TaxRate::class);
    }

    /**
     * Get products associated with this tax class.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
