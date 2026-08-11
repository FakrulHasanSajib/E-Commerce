<?php

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
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

    $this->customer = User::factory()->create(['email' => 'customer@example.com']);

    // Create setup
    $this->simpleProduct = Product::create([
        'name' => 'Adidas Gazelle',
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
        'name' => 'Dhaka Delivery',
        'type' => 'flat_rate',
        'cost' => 60.00,
        'is_active' => true,
    ]);

    // Place an initial order
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 3, // stock decreases to 7
    ]);

    $checkoutData = [
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
    ];

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', $checkoutData);

    $this->orderId = $response->json('data.id');
});

test('unauthorized users cannot manage admin orders list or update status', function () {
    $this->getJson('/api/admin/orders')
        ->assertStatus(401);

    $this->actingAs($this->customer)
        ->getJson('/api/admin/orders')
        ->assertStatus(403);
});

test('admin can transition order status and track timeline entries', function () {
    // 1. Get index list
    $this->actingAs($this->staffAdmin)
        ->getJson('/api/admin/orders')
        ->assertStatus(200)
        ->assertJsonCount(1, 'data');

    // 2. Update status to confirmed
    $response = $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/orders/{$this->orderId}/status", [
            'status' => 'confirmed',
            'note' => 'Order verified by phone.',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonCount(2, 'data.histories') // initial pending + confirmed
        ->assertJsonPath('data.histories.1.status', 'confirmed')
        ->assertJsonPath('data.histories.1.note', 'Order verified by phone.');
});

test('cancelling an order increments stock levels back to initial quantities', function () {
    // Initial stock is 10. Order takes 3 -> stock is 7.
    expect($this->simpleProduct->fresh()->stock_quantity)->toBe(7);

    // Cancel order
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/orders/{$this->orderId}/status", [
            'status' => 'cancelled',
            'note' => 'Customer requested cancellation.',
        ])
        ->assertStatus(200);

    // Stock should be restored back to 10!
    expect($this->simpleProduct->fresh()->stock_quantity)->toBe(10);
});

test('admin can verify manual bank transfer and mark payment status as paid', function () {
    // 1. Create a bank transfer order
    $token = Str::random(40);
    $cart = Cart::create(['token' => $token]);
    $cart->items()->create([
        'product_id' => $this->simpleProduct->id,
        'quantity' => 1,
    ]);

    $response = $this->withHeader('X-Cart-Token', $token)
        ->postJson('/api/checkout', [
            'customer' => [
                'name' => 'Jane Doe',
                'email' => 'jane@doe.com',
                'phone' => '01712345678',
            ],
            'address' => [
                'address' => 'Uttara, Dhaka',
                'country_code' => 'BD',
            ],
            'shipping_method_id' => $this->method->id,
            'payment_method' => 'bank_transfer',
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.payment_method', 'bank_transfer')
        ->assertJsonPath('data.payment_status', 'unpaid');

    $bankOrderId = $response->json('data.id');

    // 2. Admin verifies bank statement and updates payment_status to paid
    $this->actingAs($this->staffAdmin)
        ->putJson("/api/admin/orders/{$bankOrderId}/status", [
            'payment_status' => 'paid',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.payment_status', 'paid');
});
