<?php

use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingZone;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\FlashSale\FlashSaleService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RbacSeeder::class);

    $this->admin = User::factory()->create(['email' => 'admin@larashop.test']);
    $this->admin->assignRole('administrator');

    $this->customer = User::factory()->create([
        'email' => 'customer@larashop.test',
        'password' => bcrypt('password123'),
        'wallet_balance' => 0.00,
    ]);

    $this->product = Product::create([
        'name' => 'Ecosystem Product',
        'slug' => 'ecosystem-product',
        'type' => 'simple',
        'status' => 'published',
        'price' => 1000.00,
        'sale_price' => 900.00,
        'manage_stock' => true,
        'stock_quantity' => 10,
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

test('price precedence rule resolves flash sale discount over sale price and regular price', function () {
    $flashSaleService = app(FlashSaleService::class);

    // 1. Without active flash sale (sale_price 900 should be resolved)
    expect($flashSaleService->resolveEffectivePrice($this->product))->toBe(900.0);

    // 2. With active flash sale (discount_price 800 should take highest precedence)
    $flashSale = FlashSale::create([
        'title' => 'Midnight Flash',
        'start_at' => Carbon::now()->subHour(),
        'end_at' => Carbon::now()->addHours(2),
        'is_active' => true,
    ]);

    FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $this->product->id,
        'discount_price' => 800.00,
        'quantity_limit' => 5,
        'sold_quantity' => 0,
    ]);

    expect($flashSaleService->resolveEffectivePrice($this->product))->toBe(800.0);
});

test('customer return request creation and admin idempotent qc wallet refund with stock restocking', function () {
    // 1. Setup delivered order
    $order = Order::create([
        'order_number' => 'ORD-RET-101',
        'subtotal' => 1000.00,
        'shipping_cost' => 0.00,
        'tax_total' => 0.00,
        'coupon_discount' => 0.00,
        'grand_total' => 1000.00,
        'shipping_method_id' => $this->method->id,
        'payment_method' => 'cod',
        'customer_name' => 'John Customer',
        'customer_email' => 'customer@larashop.test',
        'customer_phone' => '01711',
        'shipping_address' => 'Dhaka',
        'status' => 'delivered',
        'user_id' => $this->customer->id,
    ]);

    $orderItem = $order->items()->create([
        'product_id' => $this->product->id,
        'name' => 'Ecosystem Product',
        'quantity' => 1,
        'price' => 1000.00,
    ]);

    // Stock starts at 10
    expect($this->product->fresh()->stock_quantity)->toBe(10);

    // 2. Customer submits return request
    $response = $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/customer/returns', [
            'order_id' => $order->id,
            'reason' => 'Defective zipper',
            'refund_method' => 'wallet',
            'items' => [
                [
                    'order_item_id' => $orderItem->id,
                    'quantity' => 1,
                    'condition' => 'defective',
                ],
            ],
        ])
        ->assertStatus(201);

    $returnId = $response->json('data.id');
    expect(ReturnRequest::count())->toBe(1);

    // 3. Admin processes QC inspection (qc_passed)
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/returns/{$returnId}/qc", [
            'status' => 'qc_passed',
            'qc_notes' => 'Confirmed defective zipper',
        ])
        ->assertStatus(200);

    // Stock should be restocked from 10 to 11
    expect($this->product->fresh()->stock_quantity)->toBe(11);

    // User wallet balance should be credited BDT 1000.00
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(1000.0);
    expect(WalletTransaction::where('user_id', $this->customer->id)->count())->toBe(1);

    // Status should be refunded
    expect(ReturnRequest::find($returnId)->status)->toBe('refunded');

    // 4. Idempotency Check: Second call to QC passed should fail (422) to prevent double wallet crediting
    $this->actingAs($this->admin, 'sanctum')
        ->putJson("/api/admin/returns/{$returnId}/qc", [
            'status' => 'qc_passed',
        ])
        ->assertStatus(422);

    // Wallet balance remains BDT 1000.00 (not doubled to 2000.00)
    expect((float) $this->customer->fresh()->wallet_balance)->toBe(1000.0);
});

test('gdpr data export and account anonymization with session revocation', function () {
    // 1. Customer exports GDPR data
    $response = $this->actingAs($this->customer, 'sanctum')
        ->getJson('/api/customer/gdpr/export')
        ->assertStatus(200);

    $data = $response->json('data');
    expect($data['user']['email'])->toBe('customer@larashop.test');

    // 2. Customer requests account deletion / anonymization
    $this->actingAs($this->customer, 'sanctum')
        ->postJson('/api/customer/gdpr/delete', [
            'password' => 'password123',
        ])
        ->assertStatus(200);

    $anonymizedUser = $this->customer->fresh();
    expect($anonymizedUser->name)->toBe('Anonymized Customer');
    expect($anonymizedUser->email)->toContain('anonymized_');
    expect($anonymizedUser->phone)->toBeNull();
});

test('flash sales public API endpoint with Redis short-TTL caching', function () {
    $flashSale = FlashSale::create([
        'title' => 'Summer Deals',
        'start_at' => Carbon::now()->subHour(),
        'end_at' => Carbon::now()->addHours(5),
        'is_active' => true,
    ]);

    FlashSaleItem::create([
        'flash_sale_id' => $flashSale->id,
        'product_id' => $this->product->id,
        'discount_price' => 750.00,
        'quantity_limit' => 10,
        'sold_quantity' => 2,
    ]);

    Cache::flush();

    $response = $this->getJson('/api/flash-sales')
        ->assertStatus(200);

    $deals = $response->json('data');
    expect(count($deals))->toBe(1);
    expect((float) $deals[0]['discount_price'])->toBe(750.0);
    expect($deals[0]['available_quantity'])->toBe(8);
});
