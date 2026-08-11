<?php

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use App\Services\Return\ReturnService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('super-admin');

    $this->customer = User::factory()->create(['email' => 'customer@larashop.test', 'wallet_balance' => 0.00]);

    $this->zone = ShippingZone::create(['name' => 'Dhaka', 'is_active' => true]);
    $this->zone->locations()->create(['country_code' => 'BD']);
    $this->method = $this->zone->methods()->create([
        'name' => 'Standard',
        'type' => 'flat_rate',
        'cost' => 50.00,
        'is_active' => true,
    ]);
});

test('fix 2a: partial return refunds exact item amount and marks payment_status as partial', function () {
    $p1 = Product::create(['name' => 'Item 1', 'slug' => 'p1', 'type' => 'simple', 'sku' => 'P1', 'price' => 1000.00, 'stock_quantity' => 10, 'is_active' => true, 'status' => 'published']);
    $p2 = Product::create(['name' => 'Item 2', 'slug' => 'p2', 'type' => 'simple', 'sku' => 'P2', 'price' => 2000.00, 'stock_quantity' => 10, 'is_active' => true, 'status' => 'published']);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-PARTIAL-TEST',
        'status' => 'shipped',
        'subtotal' => 3000.00,
        'discount' => 0.00,
        'grand_total' => 3050.00,
        'customer_name' => 'Customer',
        'customer_email' => 'customer@test.com',
        'customer_phone' => '01700000000',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'wallet',
        'payment_status' => 'paid',
    ]);

    $item1 = $order->items()->create(['product_id' => $p1->id, 'name' => 'Item 1', 'price' => 1000.00, 'quantity' => 1]);
    $item2 = $order->items()->create(['product_id' => $p2->id, 'name' => 'Item 2', 'price' => 2000.00, 'quantity' => 1]);

    app(OrderService::class)->updateStatus($order, 'delivered');

    $returnService = app(ReturnService::class);
    $returnRequest = $returnService->createReturnRequest(
        $this->customer,
        $order->id,
        'Defective Item 1',
        'wallet',
        [['order_item_id' => $item1->id, 'quantity' => 1]]
    );

    $returnService->processQcStatus($returnRequest->id, 'qc_passed');

    // Wallet gets exact 1000.00 (not 3050.00)
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(1000.00);
    expect($order->fresh()->payment_status)->toBe('partial');
});

test('fix 2b: full order cancellation refunds total grand_total and marks payment_status as refunded', function () {
    $p1 = Product::create(['name' => 'Item 1', 'slug' => 'p1-full', 'type' => 'simple', 'sku' => 'P1-F', 'price' => 1000.00, 'stock_quantity' => 10, 'is_active' => true, 'status' => 'published']);

    $order = Order::create([
        'user_id' => $this->customer->id,
        'order_number' => 'ORD-FULL-CANCEL',
        'status' => 'confirmed',
        'subtotal' => 1000.00,
        'discount' => 0.00,
        'grand_total' => 1050.00,
        'customer_name' => 'Customer',
        'customer_email' => 'customer@test.com',
        'customer_phone' => '01700000000',
        'shipping_address' => 'Dhaka',
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'wallet',
        'payment_status' => 'paid',
    ]);

    $order->items()->create(['product_id' => $p1->id, 'name' => 'Item 1', 'price' => 1000.00, 'quantity' => 1]);

    // Admin cancels full order (amount parameter left null)
    app(PaymentService::class)->refund($order, 'Customer requested cancellation', $this->admin, 'cancelled');

    // Full 1050.00 refunded
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(1050.00);
    expect($order->fresh()->payment_status)->toBe('refunded');
    expect($order->fresh()->status)->toBe('cancelled');
});
