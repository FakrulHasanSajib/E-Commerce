<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\ShippingZone;
use App\Models\TaxRate;
use App\Services\Marketing\GiftCardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CheckoutCalculationService
{
    /**
     * Calculate checkout totals, shipping rates, and tax breakdown.
     */
    public function calculate(
        Cart $cart,
        string $countryCode,
        ?string $division,
        ?string $district,
        ?string $upazila,
        ?int $shippingMethodId = null,
        ?string $giftCardCode = null
    ): array {
        // Eager load items, products, and variants to prevent N+1 queries
        $cart->loadMissing('items.product', 'items.variant');

        $subtotal = $cart->getSubtotal();
        $discount = $cart->getDiscount();

        // 1. Calculate Total Weight
        $totalWeight = (float) $cart->items->sum(function ($item) {
            return ($item->product->weight ?? 0.00) * $item->quantity;
        });

        // 2. Calculate Item-level Taxes (avoiding N+1 queries and using Cache)
        $taxRates = Cache::rememberForever('active_tax_rates_by_class', function () {
            return TaxRate::where('is_active', true)->get()->keyBy('tax_class_id');
        });

        $taxCost = 0.00;
        foreach ($cart->items as $item) {
            if (! $item->product->tax_class_id) {
                continue;
            }

            $rateModel = $taxRates->get($item->product->tax_class_id);
            if (! $rateModel) {
                continue;
            }

            $itemSubtotal = $item->getItemPrice() * $item->quantity;

            // Apply cart coupon discount proportionally to get the correct taxable amount
            $itemProportionalDiscount = $subtotal > 0
                ? ($discount / $subtotal) * $itemSubtotal
                : 0.00;

            $taxableAmount = max(0.00, $itemSubtotal - $itemProportionalDiscount);
            $itemTax = $taxableAmount * ((float) $rateModel->rate / 100);

            $taxCost += round($itemTax, 2);
        }

        // 3. Resolve Shipping Zone and Methods
        $zone = ShippingZone::findMatchingZone($countryCode, $division, $district, $upazila);

        $availableMethods = [];
        $selectedMethod = null;

        if ($zone) {
            foreach ($zone->methods as $method) {
                if (! $method->is_active) {
                    continue;
                }

                try {
                    $cost = $method->calculateCost($subtotal, $totalWeight);
                    $availableMethods[] = [
                        'id' => $method->id,
                        'name' => $method->name,
                        'type' => $method->type,
                        'cost' => number_format($cost, 2, '.', ''),
                        'is_available' => true,
                    ];

                    if ($shippingMethodId && $method->id === $shippingMethodId) {
                        $selectedMethod = [
                            'method' => $method,
                            'cost' => $cost,
                        ];
                    }
                } catch (\Exception $e) {
                    $availableMethods[] = [
                        'id' => $method->id,
                        'name' => $method->name,
                        'type' => $method->type,
                        'cost' => null,
                        'is_available' => false,
                        'reason' => $e->getMessage(),
                    ];

                    if ($shippingMethodId && $method->id === $shippingMethodId) {
                        throw ValidationException::withMessages([
                            'shipping_method_id' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // 4. Determine Shipping Cost
        $shippingCost = 0.00;
        if ($shippingMethodId) {
            if (! $selectedMethod) {
                throw ValidationException::withMessages([
                    'shipping_method_id' => 'The selected shipping method is unavailable for your destination or weight limits.',
                ]);
            }

            // Check if cart has a free shipping coupon applied
            $hasFreeShippingCoupon = ($cart->coupon && $cart->coupon->type === 'free_shipping');
            $shippingCost = $hasFreeShippingCoupon ? 0.00 : (float) $selectedMethod['cost'];
        }

        // 5. Calculate Sub-net and Gift Card Deduction
        $netTotalBeforeGiftCard = max(0.00, round($subtotal - $discount + $shippingCost + $taxCost, 2));
        $giftCardDeducted = 0.00;

        if ($giftCardCode) {
            $giftCardService = resolve(GiftCardService::class);
            $giftCard = $giftCardService->getValidGiftCard($giftCardCode);
            if ($giftCard) {
                $giftCardDeducted = min((float) $giftCard->current_balance, $netTotalBeforeGiftCard);
            }
        }

        // 6. Calculate Final Grand Total
        $grandTotal = max(0.00, round($netTotalBeforeGiftCard - $giftCardDeducted, 2));

        return [
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount' => number_format($discount, 2, '.', ''),
            'shipping_cost' => number_format($shippingCost, 2, '.', ''),
            'tax_cost' => number_format($taxCost, 2, '.', ''),
            'gift_card_deducted' => number_format($giftCardDeducted, 2, '.', ''),
            'grand_total' => number_format($grandTotal, 2, '.', ''),
            'total_weight' => number_format($totalWeight, 2, '.', ''),
            'shipping_zone' => $zone ? $zone->name : null,
            'available_shipping_methods' => $availableMethods,
        ];
    }
}
