<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Wishlist;
use App\Services\Customer\CustomerService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customer = User::factory()->create([
        'email' => 'customer@larashop.test',
        'wallet_balance' => 500.00,
        'loyalty_points' => 0,
    ]);

    $this->product = Product::create([
        'name' => 'Reward Hat',
        'type' => 'simple',
        'status' => 'published',
        'price' => 100.00,
        'manage_stock' => true,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Flat Rate',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('multiple saved addresses CRUD and default toggle observer logic', function () {
    // 1. Create default address A
    $addressA = $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/customer/addresses', [
            'label' => 'Home',
            'contact_name' => 'Tanvir Home',
            'contact_phone' => '01711',
            'address' => 'Mirpur',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'country_code' => 'BD',
            'is_default' => true,
        ]);
    $addressA->assertStatus(201);
    $idA = $addressA->json('data.id');

    expect(UserAddress::find($idA)->is_default)->toBeTrue();

    // 2. Create address B with is_default = true (should toggle A to false)
    $addressB = $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/customer/addresses', [
            'label' => 'Office',
            'contact_name' => 'Tanvir Office',
            'contact_phone' => '01712',
            'address' => 'Gulshan',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'country_code' => 'BD',
            'is_default' => true,
        ]);
    $addressB->assertStatus(201);
    $idB = $addressB->json('data.id');

    expect(UserAddress::find($idB)->is_default)->toBeTrue();
    expect(UserAddress::find($idA)->is_default)->toBeFalse(); // Toggled to false automatically!

    // 3. Update A back to default = true (should toggle B to false)
    $this->actingAs($this->customer, 'sanctum')
        ->putJson("/api/customer/addresses/{$idA}", [
            'label' => 'Home',
            'contact_name' => 'Tanvir Home',
            'contact_phone' => '01711',
            'address' => 'Mirpur Updated',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'country_code' => 'BD',
            'is_default' => true,
        ])
        ->assertStatus(200);

    expect(UserAddress::find($idA)->is_default)->toBeTrue();
    expect(UserAddress::find($idB)->is_default)->toBeFalse(); // Toggled B to false automatically!

    // 4. Delete address B
    $this->actingAs($this->customer, 'sanctum')
        ->deleteJson("/api/customer/addresses/{$idB}")
        ->assertStatus(204);
});

test('wishlist management and composite unique constraints', function () {
    // 1. Add product to wishlist
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/customer/wishlist/{$this->product->id}")
        ->assertStatus(201);

    expect(Wishlist::where('user_id', $this->customer->id)->count())->toBe(1);

    // 2. Duplicate wishlist add returns success but does not create redundant database record
    $this->actingAs($this->customer, 'sanctum')
        ->postJson("/api/customer/wishlist/{$this->product->id}")
        ->assertStatus(200);

    expect(Wishlist::where('user_id', $this->customer->id)->count())->toBe(1);

    // 3. List wishlist
    $this->actingAs($this->customer, 'sanctum')
        ->getJson('/api/customer/wishlist')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // 4. Delete from wishlist
    $this->actingAs($this->customer, 'sanctum')
        ->deleteJson("/api/customer/wishlist/{$this->product->id}")
        ->assertStatus(204);

    expect(Wishlist::where('user_id', $this->customer->id)->count())->toBe(0);
});

test('loyalty point awards on delivery redemption to wallet and refund point reversals', function () {
    // Increase wallet balance to cover BDT 550.00 checkout cost
    $this->customer->wallet_balance = 600.00;
    $this->customer->save();

    // Populate dynamic settings
    Setting::updateOrCreate(['key' => 'loyalty.earn_rate_percent'], ['value' => 2.0, 'group' => 'loyalty', 'type' => 'numeric', 'is_public' => true]);
    Setting::updateOrCreate(['key' => 'loyalty.redemption_rate'], ['value' => 5, 'group' => 'loyalty', 'type' => 'numeric', 'is_public' => true]);

    // Clear settings cache to load fresh values
    setting()->clearCache();

    // 1. Place order (total: 5*100 product + 50 shipping = 550 BDT)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 5]);

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'John', 'email' => 'john@gmail.com', 'phone' => '01711'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'wallet',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);
    expect($this->customer->fresh()->wallet_balance)->toBe('50.00'); // 600 - 550 = 50 BDT

    // 2. Admin processes and delivers the order
    $order->status = 'confirmed';
    $order->save();

    // Transition to processing -> shipped -> delivered
    $orderService = resolve(OrderService::class);
    $orderService->updateStatus($order, 'processing');
    $orderService->updateStatus($order, 'shipped');
    $orderService->updateStatus($order, 'delivered');

    // Earn points: 550 * 2% = 11 points
    expect($this->customer->fresh()->loyalty_points)->toBe(11);

    // Verify loyalty transaction logged
    $earnTx = $this->customer->loyaltyTransactions()->where('type', 'earned')->first();
    expect($earnTx->points)->toBe(11);

    // 3. Redeem points for wallet balance (10 points @ 5 rate = BDT 2.00)
    $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/customer/loyalty/redeem', ['points' => 10])
        ->assertStatus(200);

    $updatedCust = $this->customer->fresh();
    expect($updatedCust->loyalty_points)->toBe(1);
    expect($updatedCust->wallet_balance)->toBe('52.00'); // 50.00 + 2.00 = 52.00 BDT

    // Check transaction records
    expect($this->customer->walletTransactions()->where('type', 'loyalty_redemption')->count())->toBe(1);
    expect($this->customer->loyaltyTransactions()->where('type', 'redeemed')->count())->toBe(1);

    // 4. Admin refunds the delivered order (triggers point reversals: reverses 11 points)
    $paymentService = resolve(PaymentService::class);
    $paymentService->refund($order, 'Refunded delivered order', $this->admin, 'returned');

    // Reversed 11 points. Current was 1 point, so 1 - 11 = -10 loyalty points.
    expect($this->customer->fresh()->loyalty_points)->toBe(-10);
    expect($this->customer->loyaltyTransactions()->where('type', 'reversed')->count())->toBe(1);

    // Wallet balance refunded back: 52.00 + 550.00 = 602.00 BDT
    expect($this->customer->fresh()->wallet_balance)->toBe('602.00');
});

test('referrals registration rewards and self-referral checks', function () {
    // Populate dynamic settings
    Setting::updateOrCreate(['key' => 'referral.referrer_reward'], ['value' => 100.00, 'group' => 'referral', 'type' => 'numeric', 'is_public' => true]);
    Setting::updateOrCreate(['key' => 'referral.referee_reward'], ['value' => 40.00, 'group' => 'referral', 'type' => 'numeric', 'is_public' => true]);

    // Clear cache
    setting()->clearCache();

    $referrer = User::factory()->create([
        'email' => 'referrer@larashop.test',
        'wallet_balance' => 0,
    ]);

    $refCode = $referrer->referral_code;
    expect($refCode)->not->toBeNull();

    $newCustomer = User::factory()->create([
        'email' => 'newcust@larashop.test',
        'wallet_balance' => 0,
    ]);

    // 1. Process valid referral
    $service = resolve(CustomerService::class);
    $service->processReferral($newCustomer, $refCode);

    expect($referrer->fresh()->wallet_balance)->toBe('100.00');
    expect($newCustomer->fresh()->wallet_balance)->toBe('40.00');
    expect($newCustomer->fresh()->referred_by_id)->toBe($referrer->id);

    // Check transaction logs
    expect($referrer->walletTransactions()->where('type', 'referral_bonus')->count())->toBe(1);
    expect($newCustomer->walletTransactions()->where('type', 'referral_bonus')->count())->toBe(1);

    // 2. Self referral blocks
    expect(function () use ($service, $newCustomer) {
        $service->processReferral($newCustomer, $newCustomer->referral_code);
    })->toThrow(ValidationException::class);
});

test('customer profile and password secure update', function () {
    // 1. Update Profile name
    $this->actingAs($this->customer, 'sanctum')
        ->putJson('/api/customer/profile', [
            'name' => 'Tanvir Updated',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Tanvir Updated');

    // 2. Change password
    $this->actingAs($this->customer, 'sanctum')
        ->putJson('/api/customer/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])
        ->assertStatus(200);

    // Verify login with new password works
    expect(Hash::check('newpassword123', $this->customer->fresh()->password))->toBeTrue();
});
