<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->user = User::factory()->create(['email' => 'customer@larashop.test']);

    // 1. Create simple product
    $this->simpleProduct = Product::create([
        'name' => 'Adidas Gazelle',
        'type' => 'simple',
        'status' => 'published',
        'price' => 100.00,
        'manage_stock' => true,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);

    // 2. Create shipping zone
    $this->zone = ShippingZone::create(['name' => 'Inside Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Dhaka Delivery',
        'type' => 'flat_rate',
        'cost' => 60.00,
        'is_active' => true,
    ]);

    // Clear caches
    ShippingZone::clearCache();
    Cache::forget('active_tax_rates_by_class');
});

test('successful checkout decrements stock and evicts cart', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 2,
    ]);

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => [
                'name' => 'John Doe',
                'email' => 'john@doe.com',
                'phone' => '01712345678',
            ],
            'address' => [
                'address' => 'House 12, Road 4, Sector 3, Uttara',
                'country_code' => 'BD',
                'division' => 'Dhaka',
                'district' => 'Dhaka',
            ],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'cod',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.grand_total', '260.00'); // 200 subtotal + 60 shipping + 0 tax

    $orderId = $response->json('data.id');

    // 1. Verify order items snapshot
    $order = Order::with('items')->find($orderId);
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->name)->toBe('Adidas Gazelle');
    expect($order->items->first()->price)->toBe('100.00');

    // 2. Verify stock decrement
    expect($this->simpleProduct->fresh()->stock_quantity)->toBe(8);

    // 3. Verify cart eviction (items wiped)
    expect($cart->fresh()->items()->count())->toBe(0);

    // 4. Verify initial timeline record
    expect($order->histories()->count())->toBe(1);
    expect($order->histories->first()->status)->toBe('pending');
});

test('insufficient stock prevents checkout', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 12, // Exceeds stock (10)
    ]);

    $response = $this->withHeader('X-Cart-Token', $token)
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
            'payment_method' => 'cod',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['cart']);

    // Stock should remain unchanged
    expect($this->simpleProduct->fresh()->stock_quantity)->toBe(10);
});

test('idempotency converted_to_order_id blocks duplicate order creation on network retries', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 2,
    ]);

    // First request places the order
    $response1 = $this->withHeader('X-Cart-Token', $token)
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
            'payment_method' => 'cod',
        ]);

    $response1->assertStatus(200);
    $orderNumber1 = $response1->json('data.order_number');

    // Second request (retry) for the same cart token should return the exact same order
    $response2 = $this->withHeader('X-Cart-Token', $token)
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
            'payment_method' => 'cod',
        ]);

    $response2->assertStatus(200);
    $orderNumber2 = $response2->json('data.order_number');

    expect($orderNumber1)->toBe($orderNumber2);

    // Verify only one order was created in DB
    expect(Order::count())->toBe(1);
    // Stock should have only decremented once
    expect($this->simpleProduct->fresh()->stock_quantity)->toBe(8);
});

test('checkout concurrency safety blocks parallel checkout requests', function () {
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 1,
    ]);

    // 1. Manually acquire lock beforehand to simulate concurrent execution lock
    $lock = Cache::lock('checkout_lock_'.$cart->id, 10);
    $lock->acquire();

    // 2. Submit request: it should block and throw a validation lock error or timeout
    $response = $this->withHeader('X-Cart-Token', $token)
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
            'payment_method' => 'cod',
        ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Lock obtain timeout.',
        ]);

    $lock->release();
});
