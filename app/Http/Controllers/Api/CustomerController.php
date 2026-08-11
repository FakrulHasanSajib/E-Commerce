<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserAddressResource;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\WalletTransactionResource;
use App\Http\Resources\WishlistResource;
use App\Models\Order;
use App\Models\UserAddress;
use App\Models\Wishlist;
use App\Services\Customer\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CustomerController extends Controller
{
    public function __construct(protected CustomerService $customerService) {}

    /**
     * Get the logged-in customer's profile.
     */
    public function showProfile(Request $request)
    {
        return new UserProfileResource($request->user('sanctum'));
    }

    /**
     * Update the logged-in customer's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $user->update($validated);

        return new UserProfileResource($user);
    }

    /**
     * Update the logged-in customer's password.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user('sanctum');

        $request->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ], 200);
    }

    /**
     * Get the order history of the logged-in customer.
     */
    public function indexOrders(Request $request)
    {
        $user = $request->user('sanctum');

        $orders = Order::where('user_id', $user->id)
            ->with(['items'])
            ->latest()
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    /**
     * Get the customer's wishlist items.
     */
    public function indexWishlist(Request $request)
    {
        $user = $request->user('sanctum');

        $items = Wishlist::where('user_id', $user->id)
            ->with(['product.media'])
            ->latest()
            ->paginate(20);

        return WishlistResource::collection($items);
    }

    /**
     * Add a product to the customer's wishlist.
     */
    public function storeWishlist(Request $request, $productId)
    {
        $user = $request->user('sanctum');

        $item = Wishlist::firstOrCreate([
            'user_id' => $user->id,
            'product_id' => $productId,
        ]);

        return new WishlistResource($item);
    }

    /**
     * Remove a product from the customer's wishlist.
     */
    public function destroyWishlist(Request $request, $productId)
    {
        $user = $request->user('sanctum');

        Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        return response()->json(null, 204);
    }

    /**
     * List all customer saved addresses.
     */
    public function indexAddresses(Request $request)
    {
        $user = $request->user('sanctum');

        $addresses = UserAddress::where('user_id', $user->id)
            ->orderBy('is_default', 'desc')
            ->latest()
            ->get();

        return UserAddressResource::collection($addresses);
    }

    /**
     * Save a new address.
     */
    public function storeAddress(Request $request)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:50',
            'address' => 'required|string',
            'division' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'upazila' => 'nullable|string|max:100',
            'country_code' => 'required|string|size:2',
            'is_default' => 'nullable|boolean',
        ]);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $validated) {
            if (! empty($validated['is_default'])) {
                UserAddress::where('user_id', $user->id)->update(['is_default' => false]);
            }

            $address = $user->addresses()->create($validated);

            return new UserAddressResource($address);
        });
    }

    /**
     * Update an address.
     */
    public function updateAddress(Request $request, $id)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'contact_name' => 'required|string|max:100',
            'contact_phone' => 'required|string|max:50',
            'address' => 'required|string',
            'division' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'upazila' => 'nullable|string|max:100',
            'country_code' => 'required|string|size:2',
            'is_default' => 'nullable|boolean',
        ]);

        $address = UserAddress::where('user_id', $user->id)->findOrFail($id);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($user, $address, $validated) {
            if (! empty($validated['is_default'])) {
                UserAddress::where('user_id', $user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
            }

            $address->update($validated);

            return new UserAddressResource($address);
        });
    }

    /**
     * Delete an address.
     */
    public function destroyAddress(Request $request, $id)
    {
        $user = $request->user('sanctum');

        $address = UserAddress::where('user_id', $user->id)->findOrFail($id);
        $address->delete();

        return response()->json(null, 204);
    }

    /**
     * Get wallet transaction history.
     */
    public function walletTransactions(Request $request)
    {
        $user = $request->user('sanctum');

        $txs = $user->walletTransactions()->latest()->paginate(15);

        return WalletTransactionResource::collection($txs);
    }

    /**
     * Get loyalty transactions history.
     */
    public function loyaltyTransactions(Request $request)
    {
        $user = $request->user('sanctum');

        $txs = $user->loyaltyTransactions()->latest()->paginate(15);

        return LoyaltyTransactionResource::collection($txs);
    }

    /**
     * Redeem loyalty points for wallet balance.
     */
    public function redeemPoints(Request $request)
    {
        $user = $request->user('sanctum');

        $validated = $request->validate([
            'points' => 'required|integer|min:1',
        ]);

        $this->customerService->redeemLoyaltyPoints($user, $validated['points']);

        return response()->json([
            'message' => 'Loyalty points redeemed successfully.',
            'wallet_balance' => $user->fresh()->wallet_balance,
            'loyalty_points' => $user->fresh()->loyalty_points,
        ], 200);
    }
}
