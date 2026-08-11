<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Services\CheckoutCalculationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected CheckoutCalculationService $calculationService
    ) {}

    /**
     * Calculate cart checkout totals, shipping rates, and taxes.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'country_code' => 'required|string|size:2',
            'division' => 'nullable|string|max:100',
            'district' => 'nullable|string|max:100',
            'upazila' => 'nullable|string|max:100',
            'shipping_method_id' => 'nullable|integer',
        ]);

        $user = $request->user('sanctum');
        $token = $request->header('X-Cart-Token');

        // Resolve cart
        $cart = $this->cartService->getOrCreateCart($user, $token);

        if ($cart->items()->count() === 0) {
            return response()->json([
                'message' => 'Your cart is empty.',
            ], 422);
        }

        try {
            $breakdown = $this->calculationService->calculate(
                $cart,
                $validated['country_code'],
                $validated['division'] ?? null,
                $validated['district'] ?? null,
                $validated['upazila'] ?? null,
                $validated['shipping_method_id'] ?? null
            );

            return response()->json([
                'data' => $breakdown,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
