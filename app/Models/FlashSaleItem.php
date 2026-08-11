<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'flash_sale_id',
    'product_id',
    'discount_price',
    'quantity_limit',
    'sold_quantity',
])]
class FlashSaleItem extends Model
{
    protected function casts(): array
    {
        return [
            'discount_price' => 'decimal:2',
            'quantity_limit' => 'integer',
            'sold_quantity' => 'integer',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
