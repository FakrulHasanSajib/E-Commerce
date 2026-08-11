<?php

namespace App\Models;

use App\Services\FlashSale\FlashSaleService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id',
    'product_id',
    'product_variant_id',
    'quantity',
])]
class CartItem extends Model
{
    /**
     * Get the casts array.
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * Get the cart parent.
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the product.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the variant.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Resolve the current unit price of this item (Flash Sale -> Sale Price -> Regular Price).
     */
    public function getItemPrice(): float
    {
        $product = $this->relationLoaded('product') ? $this->product : $this->product()->first();
        $variant = $this->product_variant_id ? ($this->relationLoaded('variant') ? $this->variant : $this->variant()->first()) : null;

        if (! $product) {
            return 0.00;
        }

        $flashSaleService = app(FlashSaleService::class);

        return $flashSaleService->resolveEffectivePrice($product, $variant);
    }
}
