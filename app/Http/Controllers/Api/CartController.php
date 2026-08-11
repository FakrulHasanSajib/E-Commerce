<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    /**
     * Display the current cart (resolving guest cart tokens and merging if logged in).
     */
    public function show(Request $request)
    {
        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        // Resolve cart
        $cart = $this->cartService->getOrCreateCart($user, $token);

        // If user is logged in, and we have a valid guest cart token, merge them
        if ($user && $token && $token !== $cart->token) {
            $guestCart = Cart::where('token', $token)->whereNull('user_id')->first();
            if ($guestCart) {
                $cart = $this->cartService->mergeCarts($guestCart, $user);
            }
        }

        // Validate stock and active status
        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        // Eager load relations
        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ]);
    }

    /**
     * Add an item to the cart.
     */
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'product_variant_id' => 'nullable|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $token);

        $this->cartService->addItem(
            $cart,
            $validated['product_id'],
            $validated['product_variant_id'] ?? null,
            $validated['quantity']
        );

        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ])->response()->setStatusCode(200);
    }

    /**
     * Update quantity of a cart item.
     */
    public function updateItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $token);

        $this->cartService->updateItem($cart, $itemId, $validated['quantity']);

        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ])->response()->setStatusCode(200);
    }

    /**
     * Remove an item from the cart.
     */
    public function removeItem(Request $request, $itemId)
    {
        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $token);

        $this->cartService->removeItem($cart, $itemId);

        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ])->response()->setStatusCode(200);
    }

    /**
     * Apply a coupon to the cart.
     */
    public function applyCoupon(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $token);

        try {
            $this->cartService->applyCoupon($cart, $validated['code'], $user);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ])->response()->setStatusCode(200);
    }

    /**
     * Remove the coupon from the cart.
     */
    public function removeCoupon(Request $request)
    {
        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        $cart = $this->cartService->getOrCreateCart($user, $token);

        $this->cartService->removeCoupon($cart);

        $warnings = $this->cartService->validateStockAndRecalculate($cart);

        $cart->load([
            'items.product.media' => fn ($q) => $q->wherePivot('collection_name', 'thumbnail'),
            'items.product.brand.logo',
            'items.product.categories.image',
            'items.variant.image',
            'coupon',
        ]);

        return (new CartResource($cart))->additional([
            'warnings' => $warnings,
        ])->response()->setStatusCode(200);
    }
}
