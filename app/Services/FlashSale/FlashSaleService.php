<?php

namespace App\Services\FlashSale;

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FlashSaleService
{
    /**
     * Get active flash sale deals with Redis short-TTL caching.
     */
    public function getActiveFlashSaleDeals(): array
    {
        return Cache::remember('flash_sales:active', 60, function () {
            $now = Carbon::now();

            $flashSale = FlashSale::where('is_active', true)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now)
                ->with(['items.product'])
                ->first();

            if (! $flashSale) {
                return [];
            }

            return $flashSale->items->map(function ($item) {
                return [
                    'flash_sale_id' => $item->flash_sale_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product?->name,
                    'discount_price' => (float) $item->discount_price,
                    'quantity_limit' => $item->quantity_limit,
                    'sold_quantity' => $item->sold_quantity,
                    'available_quantity' => max(0, $item->quantity_limit - $item->sold_quantity),
                ];
            })->toArray();
        });
    }

    /**
     * Resolve effective unit price following the precedence hierarchy:
     * active_flash_sale.discount_price ?? variant.sale_price ?? variant.price ?? product.sale_price ?? product.price
     */
    public function resolveEffectivePrice(Product $product, ?ProductVariant $variant = null): float
    {
        $now = Carbon::now();

        $flashItem = FlashSaleItem::whereHas('flashSale', function ($q) use ($now) {
            $q->where('is_active', true)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>=', $now);
        })
            ->where('product_id', $product->id)
            ->whereColumn('sold_quantity', '<', 'quantity_limit')
            ->first();

        if ($flashItem) {
            return (float) $flashItem->discount_price;
        }

        if ($variant) {
            if ($variant->sale_price !== null && (float) $variant->sale_price > 0) {
                return (float) $variant->sale_price;
            }
            if ($variant->price !== null && (float) $variant->price > 0) {
                return (float) $variant->price;
            }
        }

        if ($product->sale_price !== null && (float) $product->sale_price > 0) {
            return (float) $product->sale_price;
        }

        return (float) $product->price;
    }

    /**
     * Invalidate flash sale cache.
     */
    public function clearCache(): void
    {
        Cache::forget('flash_sales:active');
    }
}
