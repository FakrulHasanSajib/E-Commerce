<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderPublicController extends Controller
{
    public function __construct(
        protected CartService $cartService,
        protected OrderService $orderService
    ) {}

    /**
     * Places a new order from checkout using secure OrderService pipeline.
     */
    public function checkout(Request $request)
    {
        $user = $request->user('sanctum');
        $sessionToken = $request->header('X-Cart-Token') ?? $request->header('X-Cart-Session') ?? $request->input('session_token');

        // 1. Get or create active cart
        $cart = $this->cartService->getOrCreateCart($user, $sessionToken);

        // 2. If payload includes items directly (guest or SPA direct post), populate cart items securely
        $items = $request->input('items', []);
        if (!empty($items)) {
            $cart->items()->delete();

            foreach ($items as $item) {
                $productId = $item['product_id'] ?? $item['id'] ?? null;
                $variantId = $item['product_variant_id'] ?? $item['variant_id'] ?? null;
                $quantity = max(1, intval($item['quantity'] ?? 1));

                if ($productId) {
                    $this->cartService->addItem($cart, $productId, $variantId, $quantity);
                }
            }
        }

        // 3. Extract Customer and Address Details
        $customerInfo = [
            'name' => $request->input('shipping_name') ?? $request->input('customer.name') ?? ($user?->name ?? 'Customer'),
            'phone' => $request->input('shipping_phone') ?? $request->input('customer.phone') ?? '01700000000',
            'email' => $request->input('shipping_email') ?? $request->input('customer.email') ?? ($user?->email ?? 'guest@larashop.test'),
        ];

        $addressInfo = [
            'address' => $request->input('shipping_address') ?? $request->input('address.address') ?? 'Dhaka, Bangladesh',
            'division' => $request->input('division') ?? $request->input('address.division') ?? 'Dhaka',
            'district' => $request->input('district') ?? $request->input('address.district') ?? 'Dhaka',
            'upazila' => $request->input('upazila') ?? $request->input('address.upazila') ?? 'Mirpur',
            'country_code' => 'BD',
        ];

        $shippingMethodId = intval($request->input('shipping_method_id') ?? 1);
        $paymentMethod = $request->input('payment_method') ?? 'cod';
        $deliveryDate = $request->input('delivery_date');
        $deliverySlotLabel = $request->input('delivery_slot_label');
        $giftCardCode = $request->input('gift_card_code');

        // 4. Delegate to OrderService pipeline (Idempotency, Concurrency Locks, Stock Decrements, Timeline entries)
        try {
            $order = $this->orderService->checkout(
                $cart,
                $customerInfo,
                $addressInfo,
                $shippingMethodId,
                $paymentMethod,
                $deliveryDate,
                $deliverySlotLabel,
                $giftCardCode
            );
        } catch (\Throwable $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            throw ValidationException::withMessages([
                'checkout' => [$e->getMessage()],
            ]);
        }

        $order->load(['items', 'histories']);

        return response()->json([
            'message' => 'Order placed successfully.',
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
                'status' => $order->status,
                'created_at' => $order->created_at,
            ],
            'data' => $order,
        ]);
    }

    /**
     * Public order tracking page.
     */
    public function track($orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['items', 'histories'])
            ->firstOrFail();

        return new OrderResource($order);
    }

    /**
     * Logged-in user's orders listing.
     */
    public function index(Request $request)
    {
        $user = $request->user('sanctum');
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $orders = Order::where('user_id', $user->id)
            ->with(['items'])
            ->latest()
            ->paginate(20);

        return OrderResource::collection($orders);
    }
}
