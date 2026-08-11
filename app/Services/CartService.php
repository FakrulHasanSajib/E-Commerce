<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Get or create a cart for the user or guest session token.
     */
    public function getOrCreateCart(?User $user, ?string $token): Cart
    {
        if ($user) {
            // Find existing user cart
            $cart = Cart::where('user_id', $user->id)->first();
            if ($cart) {
                return $cart;
            }

            // Create new cart linked to user
            return Cart::create([
                'user_id' => $user->id,
                'token' => Str::random(40),
            ]);
        }

        // Guest session
        if ($token) {
            $cart = Cart::where('token', $token)->first();
            if ($cart) {
                return $cart;
            }
        }

        // Create new guest cart
        return Cart::create([
            'user_id' => null,
            'token' => $token ?: Str::random(40),
        ]);
    }

    /**
     * Add an item to the cart.
     */
    public function addItem(Cart $cart, int $productId, ?int $variantId, int $quantity): CartItem
    {
        $product = Product::findOrFail($productId);
        $variant = $variantId ? ProductVariant::findOrFail($variantId) : null;

        // Ensure item uniqueness by checking if it already exists in the cart
        $item = $cart->items()
            ->where('product_id', $productId)
            ->where('product_variant_id', $variantId)
            ->first();

        if ($item) {
            $item->quantity += $quantity;
        } else {
            $item = new CartItem([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
            ]);
        }

        $item->save();

        return $item;
    }

    /**
     * Update cart item quantity.
     */
    public function updateItem(Cart $cart, int $itemId, int $quantity): CartItem
    {
        $item = $cart->items()->findOrFail($itemId);
        $item->quantity = $quantity;

        $item->save();

        return $item;
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(Cart $cart, int $itemId): void
    {
        $cart->items()->where('id', $itemId)->delete();
    }

    /**
     * Merge guest cart items into user's cart on login.
     */
    public function mergeCarts(Cart $guestCart, User $user): Cart
    {
        if ($guestCart->user_id === $user->id) {
            return $guestCart;
        }

        $guestCart->load('items.product', 'items.variant');

        $userCart = Cart::where('user_id', $user->id)->first();

        if (! $userCart) {
            // Simply assign the guest cart to the user
            $guestCart->user_id = $user->id;
            $guestCart->save();

            return $guestCart;
        }

        // Merge items
        foreach ($guestCart->items as $guestItem) {
            $existingItem = $userCart->items()
                ->where('product_id', $guestItem->product_id)
                ->where('product_variant_id', $guestItem->product_variant_id)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $guestItem->quantity;
                $existingItem->save();
                $guestItem->delete();
            } else {
                $guestItem->cart_id = $userCart->id;
                $guestItem->save();
            }
        }

        // Copy coupon if user cart doesn't have one
        if (! $userCart->coupon_id && $guestCart->coupon_id) {
            $userCart->coupon_id = $guestCart->coupon_id;
            $userCart->save();
        }

        // Delete guest cart
        $guestCart->delete();

        return $userCart;
    }

    /**
     * Apply a coupon to the cart.
     */
    public function applyCoupon(Cart $cart, string $code, ?User $user): Coupon
    {
        // Query code case-insensitively
        $coupon = Coupon::whereRaw('LOWER(code) = ?', [strtolower($code)])->first();

        if (! $coupon) {
            throw new \Exception('Invalid coupon code.');
        }

        // Validate eligibility rules
        $coupon->isValidFor($user, $cart->getSubtotal());

        $cart->coupon_id = $coupon->id;
        $cart->save();

        return $coupon;
    }

    /**
     * Remove applied coupon.
     */
    public function removeCoupon(Cart $cart): void
    {
        $cart->coupon_id = null;
        $cart->save();
    }

    /**
     * Validate active status and adjust quantities to match available stock levels.
     */
    public function validateStockAndRecalculate(Cart $cart): array
    {
        $cart->loadMissing('items.product', 'items.variant');

        $warnings = [];

        foreach ($cart->items as $item) {
            $product = $item->product;

            // 1. Ensure product is active and published
            if (! $product || ! $product->is_active || $product->status !== 'published') {
                $warnings[] = 'Product "'.($product?->name ?? 'Unknown').'" is no longer available and was removed from your cart.';
                $item->delete();

                continue;
            }

            // 2. Ensure variant is valid if selected
            if ($item->product_variant_id && ! $item->variant) {
                $warnings[] = 'The selected variant for "'.$product->name.'" is no longer available and was removed.';
                $item->delete();

                continue;
            }

            // 3. Stock levels checks
            $variant = $item->variant;
            if ($variant && $variant->manage_stock) {
                if ($variant->stock_quantity <= 0) {
                    $warnings[] = 'Variant for "'.$product->name.'" is out of stock and was removed.';
                    $item->delete();
                } elseif ($variant->stock_quantity < $item->quantity) {
                    $warnings[] = 'Quantity for "'.$product->name.'" was reduced to '.$variant->stock_quantity.' due to stock limits.';
                    $item->quantity = $variant->stock_quantity;
                    $item->save();
                }
            } elseif (! $variant && $product->manage_stock) {
                if ($product->stock_quantity <= 0) {
                    $warnings[] = '"'.$product->name.'" is out of stock and was removed.';
                    $item->delete();
                } elseif ($product->stock_quantity < $item->quantity) {
                    $warnings[] = 'Quantity for "'.$product->name.'" was reduced to '.$product->stock_quantity.' due to stock limits.';
                    $item->quantity = $product->stock_quantity;
                    $item->save();
                }
            }
        }

        // Revalidate coupon on subtotal updates (e.g. minimum spend checks)
        if ($cart->coupon_id && $cart->coupon) {
            try {
                $cart->coupon->isValidFor($cart->user, $cart->getSubtotal());
            } catch (\Throwable $e) {
                $warnings[] = 'Applied coupon "'.$cart->coupon->code.'" was removed: '.$e->getMessage();
                $cart->coupon_id = null;
                $cart->save();
            }
        }

        return $warnings;
    }

    /**
     * Helper to limit item quantity by stock levels.
     */
    private function capItemQuantityToStock(CartItem $item, Product $product, ?ProductVariant $variant): void
    {
        if ($variant && $variant->manage_stock) {
            $item->quantity = max(1, min($item->quantity, $variant->stock_quantity));
        } elseif (! $variant && $product->manage_stock) {
            $item->quantity = max(1, min($item->quantity, $product->stock_quantity));
        }
    }
}
