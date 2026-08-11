<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Payment\MockOnlineGateway;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->staffAdmin = User::factory()->create(['email' => 'staff-admin@larashop.test']);
    $this->staffAdmin->givePermissionTo('manage-products');

    $this->customer = User::factory()->create([
        'email' => 'customer@larashop.test',
        'wallet_balance' => 300.00,
    ]);

    $this->product = Product::create([
        'name' => 'Adidas Samba',
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

test('successful wallet payment checks and deducts balance immediately', function () {
    // Cart with item: grand total = 100 product + 50 shipping = 150 BDT
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $response = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@doe.com',
                'phone' => '01712345678',
            ],
            'address' => [
                'address' => 'Uttara, Dhaka',
                'country_code' => 'BD',
            ],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'wallet',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.payment_method', 'wallet')
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.grand_total', '150.00');

    // Wallet balance deducted: 300 - 150 = 150 BDT
    expect($this->customer->fresh()->wallet_balance)->toBe('150.00');

    // Check successful transaction record created
    $orderId = $response->json('data.id');
    $transaction = PaymentTransaction::where('order_id', $orderId)->first();
    expect($transaction->gateway)->toBe('wallet');
    expect($transaction->status)->toBe('success');
});

test('insufficient wallet balance blocks checkout', function () {
    // Make user balance low
    $this->customer->wallet_balance = 20.00;
    $this->customer->save();

    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $response = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@doe.com',
                'phone' => '01712345678',
            ],
            'address' => [
                'address' => 'Uttara, Dhaka',
                'country_code' => 'BD',
            ],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'wallet',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['cart']);

    // Balance remains unchanged
    expect($this->customer->fresh()->wallet_balance)->toBe('20.00');
});

test('online payment webhook verifies signature hash and process IPN successfully', function () {
    // 1. Place order
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $checkoutRes = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Jane', 'email' => 'jane@gmail.com', 'phone' => '01711'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'online',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);

    // 2. Initialize Online Payment
    $initRes = $this->postJson('/api/payments/initialize', [
        'order_id' => $orderId,
        'gateway' => 'mock_online',
    ]);
    $initRes->assertStatus(200);

    $transaction = PaymentTransaction::where('order_id', $orderId)->first();

    // 3. Fake secure IPN payload
    $gateway = new MockOnlineGateway;
    $signature = $gateway->generateSignature($transaction->transaction_number, '150.00');

    $ipnPayload = [
        'transaction_number' => $transaction->transaction_number,
        'gateway_transaction_id' => 'GATEWAY-TXN-12345',
        'amount' => '150.00',
        'status' => 'success',
        'signature' => $signature,
    ];

    // Submit IPN request
    $this->postJson('/api/payments/ipn/mock_online', $ipnPayload)
        ->assertStatus(200);

    $order = $order->fresh();
    expect($order->payment_status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
    expect($transaction->fresh()->status)->toBe('success');

    // 4. Test duplicate webhook idempotency checks (silently ignore, return 200 OK)
    $this->postJson('/api/payments/ipn/mock_online', $ipnPayload)
        ->assertStatus(200);

    // 5. Test invalid signature verification safety (should reject with 422)
    $ipnPayload['signature'] = 'invalid-signature';
    $this->postJson('/api/payments/ipn/mock_online', $ipnPayload)
        ->assertStatus(422)
        ->assertJsonPath('status', 'error');
});

test('paying less than grand total updates payment status to partial', function () {
    // 1. Place order (150 BDT)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $checkoutRes = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Jane', 'email' => 'jane@gmail.com', 'phone' => '01711'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'online',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $initRes = $this->postJson('/api/payments/initialize', [
        'order_id' => $orderId,
        'gateway' => 'mock_online',
    ]);

    $transaction = PaymentTransaction::where('order_id', $orderId)->first();

    // 2. Pay BDT 30.00 (less than 150.00 BDT)
    $gateway = new MockOnlineGateway;
    $signature = $gateway->generateSignature($transaction->transaction_number, '30.00');

    $ipnPayload = [
        'transaction_number' => $transaction->transaction_number,
        'gateway_transaction_id' => 'GATEWAY-TXN-PARTIAL',
        'amount' => '30.00',
        'status' => 'success',
        'signature' => $signature,
    ];

    $this->postJson('/api/payments/ipn/mock_online', $ipnPayload)
        ->assertStatus(200);

    // Payment status must be partial!
    expect(Order::find($orderId)->payment_status)->toBe('partial');
});

test('refunding a paid order cancels it, restores stock, and refunds wallet balance', function () {
    // 1. Place wallet order (150 BDT)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2]); // takes 2 stock (leaves 8)

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Jane', 'email' => 'jane@gmail.com', 'phone' => '01711'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'wallet',
        ]);

    $orderId = $checkoutRes->json('data.id');

    expect($this->product->fresh()->stock_quantity)->toBe(8);
    expect($this->customer->fresh()->wallet_balance)->toBe('50.00'); // 300 - 250 BDT

    // 2. Admin refunds the order
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson("/api/admin/orders/{$orderId}/refund", [
            'note' => 'Refunding due to stock mismatch',
        ])
        ->assertStatus(200);

    // 3. Verify order status cancelled, payment refunded, stock restored, wallet credited back!
    $order = Order::find($orderId);
    expect($order->status)->toBe('cancelled');
    expect($order->payment_status)->toBe('refunded');
    expect($this->product->fresh()->stock_quantity)->toBe(10);
    expect($this->customer->fresh()->wallet_balance)->toBe('300.00'); // refunded back!
});

test('bank transfer submission and administrative verification approval/rejection', function () {
    // 1. Place order (150 BDT)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Jane', 'email' => 'jane@gmail.com', 'phone' => '01711'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'bank_transfer',
        ]);

    $orderId = $checkoutRes->json('data.id');

    // 2. Customer submits bank receipt details
    $submitRes = $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/payments/bank-transfer', [
            'order_id' => $orderId,
            'bank_name' => 'DBBL',
            'account_number' => '1234567890',
            'transaction_reference' => 'TXN-BANK-999',
            'amount' => 150.00,
        ]);
    $submitRes->assertStatus(200);
    $submittalId = $submitRes->json('data.id');

    // 3. Staff Admin lists submittals
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->getJson('/api/admin/payments/bank-submittals?status=pending')
        ->assertStatus(200)
        ->assertJsonPath('data.0.id', $submittalId);

    // 4. Staff Admin approves submittal
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->putJson("/api/admin/payments/bank-submittals/{$submittalId}/verify", [
            'action' => 'approve',
        ])
        ->assertStatus(200);

    $order = Order::find($orderId);
    expect($order->payment_status)->toBe('paid');
    expect($order->status)->toBe('confirmed');
});
