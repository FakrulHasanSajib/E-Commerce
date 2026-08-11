<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Logistics\MockCourierDriver;
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
        'wallet_balance' => 500.00,
    ]);

    $this->product = Product::create([
        'name' => 'Steadfast Shoes',
        'type' => 'simple',
        'status' => 'published',
        'price' => 200.00,
        'manage_stock' => true,
        'stock_quantity' => 15,
        'is_active' => true,
    ]);

    $this->zone = ShippingZone::create(['name' => 'BD Zone', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Steadfast Delivery',
        'type' => 'flat_rate',
        'cost' => 60.00,
        'is_active' => true,
        'default_courier' => 'steadfast',
    ]);

    $this->pickupPoint = PickupPoint::create([
        'name' => 'Mirpur Hub',
        'contact_name' => 'Rakib',
        'contact_phone' => '01811223344',
        'address' => 'Mirpur 10, Dhaka',
        'division' => 'Dhaka',
        'district' => 'Dhaka',
        'is_active' => true,
    ]);
});

test('administrative pickup points CRUD and public checkout fetch', function () {
    // 1. Create Pickup Point
    $response = $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson('/api/admin/pickup-points', [
            'name' => 'Banani Hub',
            'contact_name' => 'Asif',
            'contact_phone' => '01711223344',
            'address' => 'Road 11, Banani',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
        ]);

    $response->assertStatus(201);
    $pointId = $response->json('data.id');

    // 2. Update Pickup Point
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->putJson("/api/admin/pickup-points/{$pointId}", [
            'name' => 'Banani Hub (Updated)',
            'contact_name' => 'Asif Chowdhury',
            'contact_phone' => '01711223344',
            'address' => 'Road 11, Banani',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
        ])
        ->assertStatus(200);

    // 3. List active pickup points for checkout
    $this->getJson('/api/shipping/pickup-points')
        ->assertStatus(200)
        ->assertJsonCount(2, 'data'); // Mirpur Hub + Banani Hub

    // 4. Delete Pickup Point
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->deleteJson("/api/admin/pickup-points/{$pointId}")
        ->assertStatus(204);
});

test('order dispatching via courier updating tracking code and status', function () {
    // 1. Checkout an order (COD method)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]); // total: 200 + 60 = 260 BDT

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Tanvir', 'email' => 'tanvir@gmail.com', 'phone' => '01911'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
            'delivery_date' => '2026-08-12',
            'delivery_slot_label' => 'Morning (9 AM - 1 PM)',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);
    expect($order->status)->toBe('pending');
    expect($order->delivery_date->format('Y-m-d'))->toBe('2026-08-12');
    expect($order->delivery_slot_label)->toBe('Morning (9 AM - 1 PM)');

    // Pre-confirm the order
    $order->status = 'confirmed';
    $order->save();

    // 2. Dispatch via Steadfast courier
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson("/api/admin/orders/{$orderId}/dispatch", [
            'pickup_point_id' => $this->pickupPoint->id,
            'courier_name' => 'steadfast',
        ])
        ->assertStatus(200);

    $updatedOrder = $order->fresh();
    expect($updatedOrder->status)->toBe('processing');
    expect($updatedOrder->courier_name)->toBe('steadfast');
    expect($updatedOrder->courier_tracking_code)->not->toBeNull();
    expect($updatedOrder->pickup_point_id)->toBe($this->pickupPoint->id);
    expect($updatedOrder->histories()->where('status', 'processing')->count())->toBe(1);
});

test('courier tracking webhook processes status transitions with signature guards', function () {
    // 1. Checkout & dispatch order
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Tanvir', 'email' => 'tanvir@gmail.com', 'phone' => '01911'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);
    $order->status = 'confirmed';
    $order->save();

    $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson("/api/admin/orders/{$orderId}/dispatch", [
            'pickup_point_id' => $this->pickupPoint->id,
            'courier_name' => 'steadfast',
        ]);

    $trackingCode = $order->fresh()->courier_tracking_code;
    $driver = new MockCourierDriver;

    // 2. Webhook triggers invalid signature rejection
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'delivered',
        'signature' => 'invalid-signature-value',
    ])->assertStatus(422);

    // 3. Webhook triggers valid transition in_transit -> shipped
    $sigTransit = $driver->generateWebhookSignature($trackingCode, 'in_transit');
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'in_transit',
        'signature' => $sigTransit,
    ])->assertStatus(200);

    expect($order->fresh()->status)->toBe('shipped');

    // 4. Webhook triggers valid transition delivered -> delivered
    $sigDelivered = $driver->generateWebhookSignature($trackingCode, 'delivered');
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'delivered',
        'signature' => $sigDelivered,
    ])->assertStatus(200);

    $finalOrder = $order->fresh();
    expect($finalOrder->status)->toBe('delivered');
    expect($finalOrder->payment_status)->toBe('paid'); // COD order marked as paid automatically!

    // 5. Duplicate webhook is silently ignored (idempotent state guard)
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'delivered',
        'signature' => $sigDelivered,
    ])->assertStatus(200);

    // Confirm timeline entries are not duplicated
    expect($finalOrder->histories()->where('status', 'delivered')->count())->toBe(1);
});

test('returned status triggers payment refunds and bypasses stock restorations', function () {
    // 1. Place a prepaid wallet order (total = 200 product + 60 shipping = 260 BDT)
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 2]); // takes 2 stock (leaves 13)

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Tanvir', 'email' => 'tanvir@gmail.com', 'phone' => '01911'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'wallet',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);
    expect($this->product->fresh()->stock_quantity)->toBe(13);
    expect($this->customer->fresh()->wallet_balance)->toBe('40.00'); // 500 - 460 BDT (2*200 + 60)

    // 2. Dispatch shipment (assigns tracking code)
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson("/api/admin/orders/{$orderId}/dispatch", [
            'pickup_point_id' => $this->pickupPoint->id,
            'courier_name' => 'steadfast',
        ]);

    $trackingCode = $order->fresh()->courier_tracking_code;
    $driver = new MockCourierDriver;

    // 3. Webhook sets order to in_transit (shipped) first
    $sigTransit = $driver->generateWebhookSignature($trackingCode, 'in_transit');
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'in_transit',
        'signature' => $sigTransit,
    ])->assertStatus(200);

    // 4. Webhook returns the order (failed delivery / returned)
    $sigReturned = $driver->generateWebhookSignature($trackingCode, 'returned');
    $this->postJson('/api/courier/webhook/steadfast', [
        'tracking_code' => $trackingCode,
        'status' => 'returned',
        'signature' => $sigReturned,
    ])->assertStatus(200);

    $returnedOrder = $order->fresh();
    expect($returnedOrder->status)->toBe('returned');
    expect($returnedOrder->payment_status)->toBe('refunded');

    // Wallet balance credited back (refunded)
    expect($this->customer->fresh()->wallet_balance)->toBe('500.00');

    // Sellable stock must NOT be restored (holds in QC status)
    expect($this->product->fresh()->stock_quantity)->toBe(13); // stock remains 13!
});

test('administrative reverse return pickups', function () {
    // 1. Create a delivered order
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token, 'user_id' => $this->customer->id]);
    $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);

    $checkoutRes = $this->actingAs($this->customer, 'sanctum')
        ->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => ['name' => 'Tanvir', 'email' => 'tanvir@gmail.com', 'phone' => '01911'],
            'address' => ['address' => 'Dhaka', 'country_code' => 'BD'],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
        ]);

    $orderId = $checkoutRes->json('data.id');
    $order = Order::find($orderId);
    $order->status = 'delivered';
    $order->courier_name = 'steadfast';
    $order->save();

    // 2. Admin requests reverse return pickup
    $this->actingAs($this->staffAdmin, 'sanctum')
        ->postJson("/api/admin/orders/{$orderId}/reverse-pickup", [
            'pickup_point_id' => $this->pickupPoint->id,
        ])
        ->assertStatus(200);

    expect($order->fresh()->status)->toBe('returned');
});
