<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Customer\CustomerService;
use App\Services\FlashSale\FlashSaleService;
use App\Services\Marketing\AffiliateService;
use App\Services\Marketing\GiftCardService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Executes the checkout process with locks to prevent double checkouts and overselling.
     */
    public function checkout(
        Cart $cart,
        array $customerInfo,
        array $addressInfo,
        int $shippingMethodId,
        string $paymentMethod,
        ?string $deliveryDate = null,
        ?string $deliverySlotLabel = null,
        ?string $giftCardCode = null
    ): Order {
        // 1. Idempotency Check
        if ($cart->converted_to_order_id) {
            $existingOrder = Order::find($cart->converted_to_order_id);
            if ($existingOrder) {
                return $existingOrder;
            }
        }

        // 2. Concurrency Safety: Redis lock on cart session to block double-checkout requests
        $lock = Cache::lock('checkout_lock_'.$cart->id, 10);

        return $lock->block(5, function () use ($cart, $customerInfo, $addressInfo, $shippingMethodId, $paymentMethod, $deliveryDate, $deliverySlotLabel, $giftCardCode) {
            $cart->loadMissing('items.product', 'items.variant');

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty.',
                ]);
            }

            $productsToSync = [];

            // 3. Database Transaction & Row Level locks
            $order = DB::transaction(function () use (
                $cart,
                $customerInfo,
                $addressInfo,
                $shippingMethodId,
                $paymentMethod,
                $deliveryDate,
                $deliverySlotLabel,
                $giftCardCode,
                &$productsToSync
            ) {
                // 1. Lock Hierarchy - Step 1: Lock User (wallet) if applicable
                $user = null;
                if ($cart->user_id) {
                    $user = User::where('id', $cart->user_id)->lockForUpdate()->first();
                }

                // 2. Lock Hierarchy - Step 2: Lock Products, Variants, and Flash Sale items being purchased
                $productIds = $cart->items->pluck('product_id')->unique();
                $lockedProducts = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

                $variantIds = $cart->items->pluck('product_variant_id')->filter()->unique();
                $lockedVariants = collect();
                if ($variantIds->isNotEmpty()) {
                    $lockedVariants = ProductVariant::whereIn('id', $variantIds)->lockForUpdate()->get()->keyBy('id');
                }

                $now = now();
                $lockedFlashItems = FlashSaleItem::whereHas('flashSale', function ($q) use ($now) {
                    $q->where('is_active', true)
                        ->where('start_at', '<=', $now)
                        ->where('end_at', '>=', $now);
                })
                    ->whereIn('product_id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');

                // Verify stock availability and flash sale limits on locked entities
                foreach ($cart->items as $item) {
                    $product = $lockedProducts->get($item->product_id);
                    if (! $product || ! $product->is_active || $product->status !== 'published') {
                        throw ValidationException::withMessages([
                            'cart' => 'Product "'.($product?->name ?? 'Unknown').'" is no longer available.',
                        ]);
                    }

                    $variant = $item->product_variant_id ? $lockedVariants->get($item->product_variant_id) : null;
                    if ($item->product_variant_id && ! $variant) {
                        throw ValidationException::withMessages([
                            'cart' => 'The selected variant for "'.$product->name.'" is no longer available.',
                        ]);
                    }

                    if ($variant && $variant->manage_stock) {
                        if ($variant->stock_quantity < $item->quantity) {
                            throw ValidationException::withMessages([
                                'cart' => 'Insufficient stock for variant of "'.$product->name.'". Only '.$variant->stock_quantity.' left.',
                            ]);
                        }
                    } elseif (! $variant && $product->manage_stock) {
                        if ($product->stock_quantity < $item->quantity) {
                            throw ValidationException::withMessages([
                                'cart' => 'Insufficient stock for "'.$product->name.'". Only '.$product->stock_quantity.' left.',
                            ]);
                        }
                    }

                    $flashItem = $lockedFlashItems->get($item->product_id);
                    if ($flashItem) {
                        if ($flashItem->sold_quantity + $item->quantity > $flashItem->quantity_limit) {
                            $available = max(0, $flashItem->quantity_limit - $flashItem->sold_quantity);
                            throw ValidationException::withMessages([
                                'cart' => 'Flash sale limit exceeded for "'.$product->name.'". Only '.$available.' left at flash sale price.',
                            ]);
                        }
                    }
                }

                // 3. Lock Hierarchy - Step 3: Lock Coupon if applied
                $coupon = null;
                if ($cart->coupon_id) {
                    $coupon = Coupon::where('id', $cart->coupon_id)->lockForUpdate()->first();
                    if ($coupon) {
                        $coupon->isValidFor($cart->user, $cart->getSubtotal());
                    }
                }

                // 4. Pricing Recalculation
                $calculationService = new CheckoutCalculationService;
                $breakdown = $calculationService->calculate(
                    $cart,
                    $addressInfo['country_code'] ?? 'BD',
                    $addressInfo['division'] ?? null,
                    $addressInfo['district'] ?? null,
                    $addressInfo['upazila'] ?? null,
                    $shippingMethodId,
                    $giftCardCode
                );

                $giftCardDeducted = (float) ($breakdown['gift_card_deducted'] ?? 0.00);

                // 5. Wallet Payment Check
                if ($paymentMethod === 'wallet') {
                    if (! $user) {
                        throw ValidationException::withMessages([
                            'cart' => 'Wallet payment is only available for registered users.',
                        ]);
                    }
                    if ($user->wallet_balance < $breakdown['grand_total']) {
                        throw ValidationException::withMessages([
                            'cart' => 'Insufficient wallet balance.',
                        ]);
                    }
                    $user->decrement('wallet_balance', $breakdown['grand_total']);
                }

                // 7. Generate Unique Order Number
                $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
                while (Order::where('order_number', $orderNumber)->exists()) {
                    $orderNumber = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
                }

                // 8. Write Order Record
                $order = Order::create([
                    'user_id' => $cart->user_id,
                    'order_number' => $orderNumber,
                    'status' => $paymentMethod === 'wallet' ? 'confirmed' : 'pending',
                    'subtotal' => $breakdown['subtotal'],
                    'discount' => $breakdown['discount'],
                    'shipping_cost' => $breakdown['shipping_cost'],
                    'tax_cost' => $breakdown['tax_cost'],
                    'grand_total' => $breakdown['grand_total'],
                    'coupon_id' => $cart->coupon_id,
                    'shipping_method_id' => $shippingMethodId,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentMethod === 'wallet' ? 'paid' : 'unpaid',
                    'customer_name' => $customerInfo['name'],
                    'customer_email' => $customerInfo['email'],
                    'customer_phone' => $customerInfo['phone'],
                    'shipping_address' => $addressInfo['address'],
                    'division' => $addressInfo['division'] ?? null,
                    'district' => $addressInfo['district'] ?? null,
                    'upazila' => $addressInfo['upazila'] ?? null,
                    'delivery_date' => $deliveryDate,
                    'delivery_slot_label' => $deliverySlotLabel,
                ]);

                // 8. Snapshot Order Items & Attributes, Decrement Inventory
                foreach ($cart->items as $item) {
                    $product = $lockedProducts->get($item->product_id);
                    $variant = $item->product_variant_id ? $lockedVariants->get($item->product_variant_id) : null;

                    // Compile variant attributes JSON snapshot
                    $variantAttributes = null;
                    if ($variant) {
                        $variant->loadMissing('attributeValues.attribute');
                        $variantAttributes = [];
                        foreach ($variant->attributeValues as $val) {
                            $variantAttributes[$val->attribute->name] = $val->value;
                        }
                    }

                    $order->items()->create([
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'name' => $product->name,
                        'price' => $item->getItemPrice(),
                        'quantity' => $item->quantity,
                        'variant_attributes' => $variantAttributes,
                    ]);

                    // Decrement Stock
                    // Note for future multi-warehouse integration: decrement logic should query warehouse batches instead.
                    if ($variant && $variant->manage_stock) {
                        $variant->decrement('stock_quantity', $item->quantity);
                        $productsToSync[] = $product;
                    } elseif (! $variant && $product->manage_stock) {
                        $product->decrement('stock_quantity', $item->quantity);
                        $productsToSync[] = $product;
                    }

                    // Increment Flash Sale sold_quantity if applicable
                    $flashItem = $lockedFlashItems->get($item->product_id);
                    if ($flashItem) {
                        $flashItem->increment('sold_quantity', $item->quantity);
                        resolve(FlashSaleService::class)->clearCache();
                    }
                }

                // 9. Process Coupon Usage Increment
                if ($coupon) {
                    $coupon->increment('uses_count');
                    if ($cart->user_id) {
                        CouponUser::create([
                            'coupon_id' => $coupon->id,
                            'user_id' => $cart->user_id,
                            'used_at' => now(),
                        ]);
                    }
                }

                // 10. Update Cart Idempotency Flag and Wipe Items
                $cart->converted_to_order_id = $order->id;
                $cart->coupon_id = null;
                $cart->save();

                $cart->items()->delete();

                // 11. Redeem Gift Card if applicable
                if ($giftCardCode && $giftCardDeducted > 0) {
                    $giftCardService = resolve(GiftCardService::class);
                    $giftCardService->redeemGiftCard($giftCardCode, $giftCardDeducted, $order);
                }

                // 12. Record Wallet Payment Transaction if applicable
                if ($paymentMethod === 'wallet') {
                    PaymentTransaction::create([
                        'order_id' => $order->id,
                        'transaction_number' => 'TXN-'.strtoupper(Str::random(12)),
                        'gateway_transaction_id' => 'WALLET-'.strtoupper(Str::random(10)),
                        'gateway' => 'wallet',
                        'amount' => $order->grand_total,
                        'status' => 'success',
                    ]);

                    $user->walletTransactions()->create([
                        'amount' => -$order->grand_total,
                        'type' => 'purchase',
                        'description' => "Debited for checkout order {$order->order_number}.",
                    ]);
                }

                // 12. Initial Timeline entry
                $order->histories()->create([
                    'status' => $paymentMethod === 'wallet' ? 'confirmed' : 'pending',
                    'note' => $paymentMethod === 'wallet' ? 'Order paid and confirmed via user wallet.' : 'Order created successfully.',
                ]);

                return $order;
            });

            // 12. Safe post-commit Scout search index sync
            DB::afterCommit(function () use ($productsToSync) {
                foreach ($productsToSync as $prod) {
                    $prod->searchable();
                }
            });

            return $order;
        });
    }

    public function updateStatus(Order $order, string $newStatus, ?string $note = null, ?User $changedBy = null): void
    {
        $oldStatus = $order->status;
        if ($oldStatus === $newStatus) {
            return;
        }

        // Strict State Transition Guards for Webhook Idempotency
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['processing', 'shipped', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'returned', 'cancelled'],
            'delivered' => ['returned'], // Returns are post-delivery
            'cancelled' => [], // Terminal state
            'returned' => [], // Terminal state
        ];

        if (! isset($allowedTransitions[$oldStatus]) || ! in_array($newStatus, $allowedTransitions[$oldStatus])) {
            return; // Silently ignore invalid transitions or repeat retries
        }

        $productsToSync = [];

        DB::transaction(function () use ($order, $newStatus, $oldStatus, $note, $changedBy, &$productsToSync) {
            $order->status = $newStatus;
            $order->save();

            // Stock Restoration ONLY upon Cancellation (pre-delivery)
            if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                $order->loadMissing('items.product', 'items.variant');
                foreach ($order->items as $item) {
                    $product = $item->product;
                    $variant = $item->variant;

                    if ($variant && $variant->manage_stock) {
                        $variant->increment('stock_quantity', $item->quantity);
                        $productsToSync[] = $product;
                    } elseif (! $variant && $product && $product->manage_stock) {
                        $product->increment('stock_quantity', $item->quantity);
                        $productsToSync[] = $product;
                    }
                }
            }

            // Loyalty points & Affiliate Commission award on delivery
            if ($newStatus === 'delivered' && $oldStatus !== 'delivered') {
                $customerService = resolve(CustomerService::class);
                $customerService->creditLoyaltyPoints($order);

                $affiliateService = resolve(AffiliateService::class);
                $affiliateService->creditCommissionOnDelivery($order);
            }

            // Create timeline record
            $order->histories()->create([
                'status' => $newStatus,
                'note' => $note ?: 'Status updated from '.$oldStatus.' to '.$newStatus.'.',
                'changed_by_user_id' => $changedBy?->id,
            ]);
        });

        // Safe post-commit Scout search re-indexing
        DB::afterCommit(function () use ($productsToSync) {
            foreach ($productsToSync as $prod) {
                $prod->searchable();
            }
        });
    }
}
